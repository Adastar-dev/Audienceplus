<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\ParticipationAudience;
use App\Models\SalleVirtuelle;
use App\Models\Utilisateur;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AudienceController extends Controller
{
    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    public function index(Request $request)
    {
        $query = Audience::with(['dossier', 'juge', 'procesVerbal']);

        if ($request->user()->role === 'JUGE') {
            $query->where('id_juge', $request->user()->id_utilisateur);
        }

        return response()->json($query->orderBy('date_heure')->get());
    }

    public function show(Audience $audience)
    {
        $audience->refresh();

        return response()->json($audience->load([
            'dossier.pieces', 'dossier.procureurAssigne', 'dossier.procureurQuiADonneAvis',
            'juge', 'salle', 'participations.utilisateur', 'procesVerbal',
        ]));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_dossier' => 'required|exists:dossiers,id_dossier',
            'id_juge' => 'nullable|exists:utilisateurs,id_utilisateur',
            'date_heure' => 'required|date|after:now',
            'mode' => 'nullable|in:PRESENTIEL,EN_LIGNE',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $audience = Audience::create([
            'id_dossier' => $request->id_dossier,
            'id_juge' => $request->id_juge,
            'date_heure' => $request->date_heure,
            'mode' => $request->mode ?? 'PRESENTIEL',
            'statut' => 'PROGRAMMEE',
        ]);

        $this->convoquerParties($audience);

        return response()->json($audience->load('dossier'), 201);
    }

    private function convoquerParties(Audience $audience): void
    {
        $dossier = $audience->dossier;

        foreach ($dossier->utilisateursAConvoquer() as $utilisateur) {
            $convocation = Convocation::create([
                'id_audience' => $audience->id_audience,
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'canal' => $this->canalPrefere($utilisateur),
                'statut' => 'ENVOYEE',
                'date_envoi' => now(),
            ]);

            $this->notifications->envoyerConvocation($convocation);
        }
    }

    private function canalPrefere(Utilisateur $utilisateur): string
    {
        return $utilisateur->telephone ? 'SMS' : 'EMAIL';
    }

    public function ouvrir(Audience $audience)
    {
        $audience->refresh();

        if ($audience->statut !== 'PROGRAMMEE') {
            return response()->json([
                'message' => $audience->statut === 'RATEE'
                    ? "Cette audience est passée sans avoir été ouverte et ne peut plus l'être."
                    : "Cette audience ne peut plus être ouverte (statut actuel : {$audience->statut}).",
            ], 409);
        }

        $besoinSalle = $audience->mode === 'EN_LIGNE'
            || $audience->demandeDistances()->where('statut', 'APPROUVEE')->exists();

        if ($besoinSalle && ! $audience->id_salle) {
            $salle = $this->attribuerSalleDisponible($audience->dossier->id_tribunal);
            if ($salle) {
                $audience->id_salle = $salle->id_salle;
            }
        }

        $audience->statut = 'EN_COURS';
        $audience->save();

        return response()->json($audience->load('salle'));
    }

    private function attribuerSalleDisponible(int $idTribunal): ?SalleVirtuelle
    {
        $sallesOccupees = Audience::where('statut', 'EN_COURS')
            ->whereNotNull('id_salle')
            ->pluck('id_salle');

        return SalleVirtuelle::where('id_tribunal', $idTribunal)
            ->whereNotIn('id_salle', $sallesOccupees)
            ->first();
    }

    public function fermer(Audience $audience)
    {
        $audience->update(['statut' => 'CLOTUREE']);

        if ($audience->type_decision === 'JUGEMENT') {
            $audience->dossier()->update(['statut' => 'JUGE']);
            $this->notifierDecision($audience, 'Le jugement a été rendu. La décision est disponible.');
        }

        return response()->json($audience->load('dossier'));
    }

    public function renvoyer(Audience $audience)
    {
        $audience->update(['statut' => 'RENVOYEE', 'type_decision' => 'RENVOI']);
        $audience->dossier()->update(['statut' => 'RENVOYE']);
        $this->notifierDecision($audience, "L'audience a été renvoyée à une date ultérieure.");

        return response()->json($audience->load('dossier'));
    }

    public function decider(Request $request, Audience $audience)
    {
        if ($audience->statut !== 'EN_COURS') {
            return response()->json(['message' => "L'audience doit être en cours pour enregistrer une décision."], 409);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:JUGEMENT,RENVOI,DELIBERE',
            'motif' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $audience->type_decision = $request->type;
        $audience->motif_decision = $request->motif;

        if ($request->type === 'RENVOI') {
            $audience->statut = 'RENVOYEE';
            $audience->dossier()->update(['statut' => 'RENVOYE']);
        } elseif ($request->type === 'DELIBERE') {
            $audience->statut = 'DELIBERE';
        }

        $audience->save();

        if ($request->type === 'RENVOI') {
            $this->notifierDecision($audience, "L'audience a été renvoyée à une date ultérieure.");
        } elseif ($request->type === 'DELIBERE') {
            $this->notifierDecision($audience, 'Le jugement a été mis en délibéré. La décision sera communiquée ultérieurement.');
        }

        return response()->json($audience->load('dossier'));
    }

    private function notifierDecision(Audience $audience, string $message): void
    {
        $audience->loadMissing('dossier');

        foreach ($audience->dossier->utilisateursAConvoquer() as $utilisateur) {
            $this->notifications->envoyerLibre($utilisateur, 'Décision', $message);
        }
    }

    public function admettreParticipant(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        $participation->update(['admis' => true]);

        return response()->json($participation);
    }

    public function refuserParticipant(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        $participation->update(['admis' => false, 'present' => false]);

        return response()->json($participation);
    }
}