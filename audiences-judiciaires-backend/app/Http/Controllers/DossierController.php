<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\PartieDossier;
use App\Models\Utilisateur;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DossierController extends Controller
{
    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Dossier::with('tribunal', 'procureurAssigne')->visiblesPar($user);

        if ($user->role === 'JUGE') {
            $query->whereHas('audiences', function ($q) use ($user) {
                $q->where('id_juge', $user->id_utilisateur);
            });
        }

        return response()->json($query->latest('id_dossier')->get());
    }

    public function show(Request $request, Dossier $dossier)
    {
        if (! $dossier->estAccessiblePar($request->user())) {
            abort(403, "Vous n'avez pas accès à ce dossier.");
        }

        return response()->json(
            $dossier->load([
                'tribunal', 'pieces', 'audiences.procesVerbal', 'procureurAssigne',
                'liaisonsParties.utilisateur', 'liaisonsParties.represente.utilisateur', 'procureurQuiADonneAvis',
            ])
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:DIVORCE,ADOPTION,RECTIFICATION_ACTE,CONTENTIEUX_MARIAGE,FILIATION,GARDE_PENSION,TUTELLE,DECLARATION_ABSENCE_DECES,CHANGEMENT_NOM,EMANCIPATION',
            'id_tribunal' => 'required|exists:tribunaux,id_tribunal',
            'demandeur' => 'required|string|max:255',
            'defendeur' => 'required|string|max:255',
            'id_demandeur_utilisateur' => 'nullable|exists:utilisateurs,id_utilisateur',
            'id_demandeur_avocat' => 'nullable|exists:utilisateurs,id_utilisateur',
            'id_defendeur_utilisateur' => 'nullable|exists:utilisateurs,id_utilisateur',
            'id_defendeur_avocat' => 'nullable|exists:utilisateurs,id_utilisateur',
            'id_procureur' => 'nullable|exists:utilisateurs,id_utilisateur',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $annee = now()->year;
        $sequence = Dossier::whereYear('date_creation', $annee)->count() + 1;
        $numero = sprintf('TRB-DKR-%d-%04d', $annee, $sequence);

        $dossier = Dossier::create([
            'numero' => $numero,
            'type' => $request->type,
            'statut' => 'EN_COURS',
            'parties' => "{$request->demandeur} c. {$request->defendeur}",
            'id_tribunal' => $request->id_tribunal,
            'id_procureur' => $request->id_procureur,
            'date_creation' => now(),
        ]);

        $this->lierPartie($dossier, 'DEMANDEUR', $request->id_demandeur_utilisateur, $request->id_demandeur_avocat);
        $this->lierPartie($dossier, 'DEFENDEUR', $request->id_defendeur_utilisateur, $request->id_defendeur_avocat);

        if ($request->id_procureur) {
            $this->notifierProcureurAssigne($dossier);
        }

        return response()->json($dossier->load('liaisonsParties.utilisateur', 'procureurAssigne'), 201);
    }

    private function notifierProcureurAssigne(Dossier $dossier): void
    {
        $procureur = Utilisateur::find($dossier->id_procureur);

        if (! $procureur) {
            return;
        }

        $this->notifications->envoyerLibre(
            $procureur,
            'Dossier assigné',
            "Le dossier {$dossier->numero} ({$dossier->parties}) vous a été assigné pour avis.",
        );
    }

    private function lierPartie(Dossier $dossier, string $rolePartie, ?int $idJusticiable, ?int $idAvocat): void
    {
        $ligneJusticiable = null;

        if ($idJusticiable) {
            $ligneJusticiable = PartieDossier::create([
                'id_dossier' => $dossier->id_dossier,
                'id_utilisateur' => $idJusticiable,
                'role_partie' => $rolePartie,
            ]);
        }

        if ($idAvocat) {
            PartieDossier::create([
                'id_dossier' => $dossier->id_dossier,
                'id_utilisateur' => $idAvocat,
                'role_partie' => $rolePartie,
                'represente_id_partie' => $ligneJusticiable?->id_partie,
            ]);
        }
    }

    public function update(Request $request, Dossier $dossier)
    {
        $validator = Validator::make($request->all(), [
            'statut' => 'sometimes|in:EN_COURS,RENVOYE,JUGE,CLOTURE',
            'id_procureur' => 'sometimes|nullable|exists:utilisateurs,id_utilisateur',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $procureurAChange = $request->has('id_procureur')
            && (int) $request->id_procureur !== (int) $dossier->id_procureur;

        $dossier->update($request->only('statut', 'id_procureur'));

        if ($procureurAChange && $dossier->id_procureur) {
            $this->notifierProcureurAssigne($dossier);
        }

        return response()->json($dossier->load('procureurAssigne'));
    }

    // Avis du procureur sur le dossier, persisté côté serveur.
    public function donnerAvis(Request $request, Dossier $dossier)
    {
        $validator = Validator::make($request->all(), [
            'avis' => 'required|string|max:4000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dossier->update([
            'avis_procureur' => $request->avis,
            'avis_procureur_par' => $request->user()->id_utilisateur,
            'avis_procureur_date' => now(),
        ]);

        return response()->json($dossier->load('procureurQuiADonneAvis'));
    }
}