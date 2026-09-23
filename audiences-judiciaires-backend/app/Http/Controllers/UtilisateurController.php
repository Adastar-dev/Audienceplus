<?php

namespace App\Http\Controllers;

use App\Models\PartieDossier;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UtilisateurController extends Controller
{
    public function index()
    {
        return response()->json(Utilisateur::with('tribunal')->get());
    }

    // Un admin peut créer un compte Avocat ou Justiciable (ex. citoyen sans
    // accès internet accueilli au greffe) avec les mêmes champs que l'auto-inscription.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:utilisateurs,email',
            'role' => 'required|in:JUGE,GREFFIER,PROCUREUR,AVOCAT,JUSTICIABLE,ADMINISTRATEUR',
            'id_tribunal' => 'nullable|exists:tribunaux,id_tribunal',
            'telephone' => 'nullable|string|max:30',
            'cni' => 'required_if:role,JUSTICIABLE|nullable|string|max:30|unique:utilisateurs,cni',
            'numero_barreau' => 'required_if:role,AVOCAT|nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $motDePasseTemporaire = str()->random(12);

        $utilisateur = Utilisateur::create([
            'nom' => $request->nom,
            'email' => $request->email,
            'role' => $request->role,
            'id_tribunal' => $request->id_tribunal,
            'telephone' => $request->telephone,
            'cni' => $request->cni,
            'numero_barreau' => $request->numero_barreau,
            'mot_de_passe' => Hash::make($motDePasseTemporaire),
            'identite_verifiee' => false,
        ]);

        return response()->json([
            'utilisateur' => $utilisateur,
            'mot_de_passe_temporaire' => $motDePasseTemporaire,
        ], 201);
    }

    public function update(Request $request, Utilisateur $utilisateur)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:utilisateurs,email,'.$utilisateur->id_utilisateur.',id_utilisateur',
            'role' => 'sometimes|in:JUGE,GREFFIER,PROCUREUR,AVOCAT,JUSTICIABLE,ADMINISTRATEUR',
            'telephone' => 'sometimes|nullable|string|max:30|unique:utilisateurs,telephone,'.$utilisateur->id_utilisateur.',id_utilisateur',
            'cni' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{10,13}$/', 'unique:utilisateurs,cni,'.$utilisateur->id_utilisateur.',id_utilisateur'],
            'numero_barreau' => 'sometimes|nullable|string|max:50',
        ], [
            'cni.regex' => "Le numéro de CNI doit être composé de 10 à 13 chiffres.",
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $utilisateur->update($request->only('nom', 'email', 'role', 'id_tribunal', 'telephone', 'cni', 'numero_barreau'));

        return response()->json($utilisateur);
    }

    public function destroy(Utilisateur $utilisateur)
    {
        $utilisateur->delete();

        return response()->json(null, 204);
    }

    // Comptes Avocat/Justiciable en attente de vérification d'identité.
    public function comptesAVerifier()
    {
        return response()->json(
            Utilisateur::whereIn('role', ['AVOCAT', 'JUSTICIABLE'])
                ->where('identite_verifiee', false)
                ->get()
        );
    }

    public function verifierIdentite(Utilisateur $utilisateur)
    {
        if (! in_array($utilisateur->role, ['AVOCAT', 'JUSTICIABLE'], true)) {
            return response()->json(['message' => "Seul un compte avocat ou justiciable peut être vérifié de cette façon."], 422);
        }

        $utilisateur->update(['identite_verifiee' => true]);

        return response()->json($utilisateur);
    }

    public function juges()
    {
        return response()->json(
            Utilisateur::where('role', 'JUGE')->get(['id_utilisateur', 'nom'])
        );
    }

    public function greffiers()
    {
        return response()->json(
            Utilisateur::where('role', 'GREFFIER')->get(['id_utilisateur', 'nom'])
        );
    }

    public function procureurs()
    {
        return response()->json(
            Utilisateur::where('role', 'PROCUREUR')->get(['id_utilisateur', 'nom'])
        );
    }

    public function justiciables(Request $request)
    {
        return response()->json($this->rechercherParRole($request, 'JUSTICIABLE', ['id_utilisateur', 'nom', 'email', 'cni']));
    }

    public function avocats(Request $request)
    {
        return response()->json($this->rechercherParRole($request, 'AVOCAT', ['id_utilisateur', 'nom', 'email', 'numero_barreau']));
    }

    // Justiciables que l'avocat connecté représente dans au moins un dossier.
    // Le lien avocat <-> justiciable est porté par parties_dossier (une ligne
    // par partie, l'avocat "représente" la ligne du justiciable via
    // represente_id_partie), pas par des colonnes sur dossiers : un dossier a
    // un demandeur et un défendeur, chacun avec son propre avocat éventuel,
    // ce qu'une simple colonne id_avocat/id_justiciable ne peut pas porter.
    public function mesClients(Request $request)
    {
        $idAvocat = $request->user()->id_utilisateur;

        $idsJusticiables = PartieDossier::where('parties_dossier.id_utilisateur', $idAvocat)
            ->whereNotNull('parties_dossier.represente_id_partie')
            ->join('parties_dossier as represente', 'represente.id_partie', '=', 'parties_dossier.represente_id_partie')
            ->distinct()
            ->pluck('represente.id_utilisateur');

        return response()->json(
            Utilisateur::where('role', 'JUSTICIABLE')
                ->whereIn('id_utilisateur', $idsJusticiables)
                ->orderBy('nom')
                ->get(['id_utilisateur', 'nom', 'email'])
        );
    }

    // Avocat(s) qui représentent le justiciable connecté dans au moins un
    // dossier - symétrique de mesClients(), sert à peupler la messagerie du
    // justiciable avec son/ses vrais avocats.
    public function mesAvocats(Request $request)
    {
        $idJusticiable = $request->user()->id_utilisateur;

        $idsPartiesJusticiable = PartieDossier::where('id_utilisateur', $idJusticiable)->pluck('id_partie');

        $idsAvocats = PartieDossier::whereIn('represente_id_partie', $idsPartiesJusticiable)
            ->distinct()
            ->pluck('id_utilisateur');

        return response()->json(
            Utilisateur::where('role', 'AVOCAT')
                ->whereIn('id_utilisateur', $idsAvocats)
                ->orderBy('nom')
                ->get(['id_utilisateur', 'nom', 'email'])
        );
    }

    private function rechercherParRole(Request $request, string $role, array $colonnes)
    {
        $q = $request->query('q');
        $query = Utilisateur::where('role', $role);

        if ($q) {
            $query->where(function ($sub) use ($q, $colonnes) {
                foreach (array_diff($colonnes, ['id_utilisateur']) as $colonne) {
                    $sub->orWhere($colonne, 'like', "%{$q}%");
                }
            });
        }

        return $query->orderBy('nom')->limit(20)->get($colonnes);
    }
}