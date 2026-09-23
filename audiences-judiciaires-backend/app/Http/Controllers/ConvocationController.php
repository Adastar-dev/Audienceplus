<?php

namespace App\Http\Controllers;

use App\Models\Convocation;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

        return response()->json($convocation->load('greffierQuiADonneAvis'));
    }

    // Décision finale : réservée au juge, et seulement une fois que le
    // greffier a donné son avis. Le juge peut reprendre la date proposée par
    // le greffier ou en fixer une autre.
    public function approuverReport(Request $request, Convocation $convocation)
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

        $convocation->update(['statut' => 'REPORT_APPROUVEE', 'decide_par' => $request->user()->id_utilisateur]);
        $convocation->audience()->update(['date_heure' => $nouvelleDate]);
        $this->notifications->envoyerLibre(
            $convocation->utilisateur,
            'Report approuvé',
            'Votre demande de report a été approuvée. Une nouvelle convocation vous parviendra pour la nouvelle date.',
        );

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
        $this->notifications->envoyerLibre(
            $convocation->utilisateur,
            'Report refusé',
            "Votre demande de report a été refusée : {$request->reponse_greffier}",
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