<?php

namespace App\Http\Controllers;

use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\Utilisateur;
use App\Services\DisponibiliteJuge;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Support\Messages;
use Illuminate\Support\Carbon;

class ConvocationController extends Controller
{
    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    public function index(Request $request)
    {
        $query = Convocation::with(['audience.dossier', 'greffierQuiADonneAvis']);

        if (! in_array($request->user()->role, ['GREFFIER', 'JUGE'], true)) {
            $query->where('id_utilisateur', $request->user()->id_utilisateur);
        } else {
            // Greffier et juge : seulement les dossiers de leur tribunal.
            $query->whereHas('audience.dossier', fn ($q) => $q->visiblesPar($request->user()));
        }

        return response()->json($query->latest('date_envoi')->get());
    }

    public function confirmer(Request $request, Convocation $convocation)
    {
        if ($convocation->id_utilisateur !== $request->user()->id_utilisateur) {
            return response()->json(['message' => "Cette convocation ne vous appartient pas."], 403);
        }

        $convocation->update(['statut' => 'CONFIRMEE']);

        return response()->json($convocation);
    }

    public function demanderReport(Request $request, Convocation $convocation)
    {
        if ($convocation->id_utilisateur !== $request->user()->id_utilisateur) {
            return response()->json(['message' => "Cette convocation ne vous appartient pas."], 403);
        }

        $validator = Validator::make($request->all(), [
            'motif_report' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $convocation->update([
            'statut' => 'REPORT_DEMANDE',
            'motif_report' => $request->motif_report,
        ]);

        $convocation->loadMissing('audience.dossier');
        $this->notifications->notifierGreffiers(
            $convocation->audience->dossier,
            Messages::demandeReport($convocation->audience, $request->user()->nom, $request->motif_report),
        );

        return response()->json($convocation);
    }

    // Le greffier ne décide plus lui-même du report : il donne un avis, en
    // proposant une nouvelle date s'il est favorable, que le juge devra
    // valider pour que le report soit réellement approuvé ou refusé.
    public function donnerAvisReport(Request $request, Convocation $convocation)
    {
        $validator = Validator::make($request->all(), [
            'avis' => 'required|in:FAVORABLE,DEFAVORABLE',
            'nouvelle_date_heure' => 'required_if:avis,FAVORABLE|nullable|date',
            'reponse_greffier' => 'required_if:avis,DEFAVORABLE|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($convocation->statut !== 'REPORT_DEMANDE') {
            return response()->json(['message' => 'Cette demande de report a déjà reçu un avis du greffe.'], 422);
        }

        $convocation->update([
            'statut' => $request->avis === 'FAVORABLE' ? 'AVIS_GREFFIER_FAVORABLE' : 'AVIS_GREFFIER_DEFAVORABLE',
            'avis_greffier' => $request->avis,
            'nouvelle_date_proposee' => $request->nouvelle_date_heure,
            'reponse_greffier' => $request->reponse_greffier,
            'avis_greffier_par' => $request->user()->id_utilisateur,
            'avis_greffier_date' => now(),
        ]);

        $convocation->loadMissing('audience.dossier', 'audience.juge');
        if ($juge = $convocation->audience->juge) {
            $this->notifications->envoyerMessage(
                $juge,
                Messages::reportADecider($convocation->audience, $request->avis, $request->nouvelle_date_heure),
            );
        }

        return response()->json($convocation->load('greffierQuiADonneAvis'));
    }

    // Décision finale : réservée au juge, et seulement une fois que le
    // greffier a donné son avis. Le juge peut reprendre la date proposée par
    // le greffier ou en fixer une autre.
    public function approuverReport(Request $request, Convocation $convocation, DisponibiliteJuge $disponibilite)
    {
        $validator = Validator::make($request->all(), [
            'nouvelle_date_heure' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! in_array($convocation->statut, ['AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE'], true)) {
            return response()->json([
                'message' => "L'avis du greffier est requis avant toute validation par le juge.",
            ], 422);
        }

        $nouvelleDate = $request->nouvelle_date_heure ?? $convocation->nouvelle_date_proposee;
        if (! $nouvelleDate) {
            return response()->json([
                'errors' => ['nouvelle_date_heure' => ['Aucune nouvelle date proposée par le greffier ; précisez-en une.']],
            ], 422);
        }

        $audience = $convocation->audience;
        if ($audience->id_juge
            && ! $disponibilite->estDisponible((int) $audience->id_juge, Carbon::parse($nouvelleDate), $audience->id_audience)) {
            return $disponibilite->reponseIndisponible((int) $audience->id_juge, Carbon::parse($nouvelleDate));
        }

        $convocation->update(['statut' => 'REPORT_APPROUVEE', 'decide_par' => $request->user()->id_utilisateur]);
        // Nouvelle date : les rappels automatiques repartent de zéro.
        $convocation->audience()->update(['date_heure' => $nouvelleDate, 'rappel_48h_le' => null, 'rappel_2h_le' => null]);
        $convocation->load('audience.dossier');
        $date = Carbon::parse($nouvelleDate);
        $this->notifications->envoyerMessage($convocation->utilisateur, Messages::reportAccepte($convocation->audience, $date));

        // Les autres parties sont reconvoquées à la nouvelle date (elles
        // doivent confirmer à nouveau) ; le juge et le procureur sont prévenus.
        $autres = Convocation::with('utilisateur')
            ->where('id_audience', $convocation->id_audience)
            ->where('id_convocation', '!=', $convocation->id_convocation)
            ->get();
        foreach ($autres as $autre) {
            $autre->update(['statut' => 'ENVOYEE', 'date_envoi' => now()]);
            $this->notifications->envoyerMessage($autre->utilisateur, Messages::audienceReportee($convocation->audience, $date, true));
        }

        $dossier = $convocation->audience->dossier;
        $magistrats = array_filter([
            $convocation->audience->id_juge,
            Dossier::communiqueAuParquet($dossier->type) ? $dossier->id_procureur : null,
        ]);
        foreach (Utilisateur::whereIn('id_utilisateur', $magistrats)->get() as $magistrat) {
            $this->notifications->envoyerMessage($magistrat, Messages::audienceReportee($convocation->audience, $date, false));
        }

        return response()->json($convocation->load('audience'));
    }

    public function refuserReport(Request $request, Convocation $convocation)
    {
        $validator = Validator::make($request->all(), [
            'reponse_greffier' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! in_array($convocation->statut, ['AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE'], true)) {
            return response()->json([
                'message' => "L'avis du greffier est requis avant toute validation par le juge.",
            ], 422);
        }

        $convocation->update([
            'statut' => 'REPORT_REFUSEE',
            'reponse_greffier' => $request->reponse_greffier,
            'decide_par' => $request->user()->id_utilisateur,
        ]);
        $convocation->loadMissing('audience.dossier');
        $this->notifications->envoyerMessage(
            $convocation->utilisateur,
            Messages::reportRefuse($convocation->audience, $request->reponse_greffier),
        );

        return response()->json($convocation);
    }

    public function relancer(Convocation $convocation)
    {
        $convocation->update(['statut' => 'ENVOYEE', 'date_envoi' => now()]);
        $this->notifications->envoyerRappel($convocation);

        return response()->json($convocation);
    }
}