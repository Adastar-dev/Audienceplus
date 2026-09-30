<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\PartieDossier;
use App\Models\Utilisateur;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Support\Messages;

class DossierController extends Controller
{
    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    // L'identifiant doit désigner un compte ayant réellement ce rôle (un
    // justiciable ne peut pas être désigné comme procureur, etc.).
    private static function compteDeRole(string $role)
    {
        return Rule::exists('utilisateurs', 'id_utilisateur')->where('role', $role);
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
            'defendeur' => [Rule::requiredIf(Dossier::aUnDefendeur((string) $request->type)), 'nullable', 'string', 'max:255'],
            'id_demandeur_utilisateur' => ['nullable', self::compteDeRole('JUSTICIABLE')],
            'id_demandeur_avocat' => ['nullable', self::compteDeRole('AVOCAT')],
            'id_defendeur_utilisateur' => ['nullable', self::compteDeRole('JUSTICIABLE')],
            'id_defendeur_avocat' => ['nullable', self::compteDeRole('AVOCAT')],
            'id_procureur' => ['nullable', self::compteDeRole('PROCUREUR')],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $greffier = $request->user();
        if ($greffier->id_tribunal && (int) $request->id_tribunal !== (int) $greffier->id_tribunal) {
            return response()->json(['errors' => ['id_tribunal' => ["Vous ne pouvez enregistrer un dossier que pour votre tribunal."]]], 403);
        }

        $avecDefendeur = Dossier::aUnDefendeur($request->type);

        if ($request->filled('id_procureur') && ! Dossier::communiqueAuParquet($request->type)) {
            return response()->json(['errors' => ['id_procureur' => ["Ce type de dossier n'est pas communiqué au ministère public : aucun procureur ne peut y être assigné."]]], 422);
        }

        // Procédure gracieuse (changement de nom, adoption...) : pas de défendeur.
        if (! $avecDefendeur && ($request->filled('defendeur') || $request->filled('id_defendeur_utilisateur') || $request->filled('id_defendeur_avocat'))) {
            return response()->json(['errors' => ['defendeur' => [
                "Ce type de procédure est introduit par requête et ne comporte pas de défendeur.",
            ]]], 422);
        }

        // Une même personne ne peut figurer qu'une fois dans un dossier (contrainte
        // unique sur parties_dossier) : typiquement, un avocat ne peut pas
        // représenter à la fois le demandeur et le défendeur.
        $idsParties = array_filter([
            $request->id_demandeur_utilisateur, $request->id_demandeur_avocat,
            $request->id_defendeur_utilisateur, $request->id_defendeur_avocat,
        ]);

        if (count($idsParties) !== count(array_unique($idsParties))) {
            return response()->json(['errors' => ['parties' => [
                'Une même personne ne peut pas figurer deux fois dans le dossier (par exemple, le même avocat pour le demandeur et le défendeur).',
            ]]], 422);
        }

        // Transaction : si la liaison d'une partie échoue, le dossier n'est pas
        // créé à moitié.
        $dossier = DB::transaction(function () use ($request, $avecDefendeur) {
            $annee = now()->year;
            $sequence = Dossier::whereYear('date_creation', $annee)->count() + 1;
            $numero = sprintf('TRB-DKR-%d-%04d', $annee, $sequence);

            // Le comptage ne suffit pas si un dossier a été supprimé : on
            // avance jusqu'au premier numéro libre (la colonne est unique).
            while (Dossier::where('numero', $numero)->lockForUpdate()->exists()) {
                $sequence++;
                $numero = sprintf('TRB-DKR-%d-%04d', $annee, $sequence);
            }

            $dossier = Dossier::create([
                'numero' => $numero,
                'type' => $request->type,
                'statut' => 'EN_COURS',
                'parties' => $avecDefendeur ? "{$request->demandeur} c. {$request->defendeur}" : "Requête de {$request->demandeur}",
                'id_tribunal' => $request->id_tribunal,
                'id_procureur' => $request->id_procureur,
                'date_creation' => now(),
            ]);

            $this->lierPartie($dossier, 'DEMANDEUR', $request->id_demandeur_utilisateur, $request->id_demandeur_avocat);
            if ($avecDefendeur) {
                $this->lierPartie($dossier, 'DEFENDEUR', $request->id_defendeur_utilisateur, $request->id_defendeur_avocat);
            }

            return $dossier;
        });

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

        $this->notifications->envoyerMessage($procureur, Messages::dossierAssigne($dossier));
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
        if (! $dossier->estAccessiblePar($request->user())) {
            return response()->json(['message' => "Vous n'avez pas accès à ce dossier."], 403);
        }

        if ($dossier->estArchive()) {
            return response()->json(['message' => 'Ce dossier est archivé : il ne peut plus être modifié.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'statut' => 'sometimes|in:EN_COURS,RENVOYE,JUGE,CLOTURE',
            'id_procureur' => ['sometimes', 'nullable', self::compteDeRole('PROCUREUR')],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('id_procureur') && ! Dossier::communiqueAuParquet($dossier->type)) {
            return response()->json(['errors' => ['id_procureur' => ["Ce type de dossier n'est pas communiqué au ministère public : aucun procureur ne peut y être assigné."]]], 422);
        }

        $procureurAChange = $request->has('id_procureur')
            && (int) $request->id_procureur !== (int) $dossier->id_procureur;

        $dossier->update($request->only('statut', 'id_procureur'));

        if ($procureurAChange && $dossier->id_procureur) {
            $this->notifierProcureurAssigne($dossier);
        }

        return response()->json($dossier->load('procureurAssigne'));
    }

    // Archivage manuel par le juge qui a présidé une audience du dossier, une
    // fois la décision rendue.
    public function archiver(Request $request, Dossier $dossier)
    {
        if ($dossier->estArchive()) {
            return response()->json(['message' => 'Ce dossier est déjà archivé.'], 409);
        }

        if (! $dossier->audiences()->where('id_juge', $request->user()->id_utilisateur)->exists()) {
            return response()->json(['message' => "Vous n'avez présidé aucune audience de ce dossier."], 403);
        }

        if (! in_array($dossier->statut, ['JUGE', 'CLOTURE'], true)) {
            return response()->json(['message' => 'Seul un dossier jugé peut être archivé.'], 409);
        }

        $dossier->archiver();

        return response()->json($dossier->fresh());
    }

    // Avis du procureur sur le dossier, conservé avec son auteur et sa date.
    public function donnerAvis(Request $request, Dossier $dossier)
    {
        if (! $dossier->estAccessiblePar($request->user())) {
            return response()->json(['message' => "Ce dossier n'est pas communiqué au ministère public."], 403);
        }

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