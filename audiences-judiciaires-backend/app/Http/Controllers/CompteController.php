<?php

namespace App\Http\Controllers;

use App\Support\Telephone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// « Mon compte » : l'utilisateur peut compléter les informations manquantes de
// son profil (téléphone, numéro de CNI), jamais modifier une information déjà
// renseignée : seul l'administrateur le peut (gestion des utilisateurs).
class CompteController extends Controller
{
    private const CHAMPS = ['telephone', 'cni'];

    public function completer(Request $request)
    {
        $utilisateur = $request->user();
        $champs = array_intersect(self::CHAMPS, array_keys($request->all()));

        if (! $champs) {
            return response()->json(['message' => 'Aucune information à compléter.'], 422);
        }

        foreach ($champs as $champ) {
            if (! empty($utilisateur->{$champ})) {
                return response()->json(['errors' => [$champ => [
                    "Cette information est déjà renseignée : seul l'administrateur peut la modifier.",
                ]]], 422);
            }
        }

        if (in_array('cni', $champs, true)
            && (! in_array($utilisateur->role, ['JUSTICIABLE', 'AVOCAT'], true) || $utilisateur->identite_verifiee)) {
            return response()->json(['errors' => ['cni' => [
                "Le numéro de carte d'identité ne peut plus être ajouté sur ce compte : adressez-vous à l'administrateur.",
            ]]], 422);
        }

        if ($request->has('telephone')) {
            $request->merge(['telephone' => Telephone::normaliser($request->telephone)]);
        }

        $validator = Validator::make($request->only($champs), [
            'telephone' => ['sometimes', 'required', 'string', Telephone::REGLE, 'unique:utilisateurs,telephone'],
            'cni' => ['sometimes', 'required', 'regex:/^\d{10,13}$/', 'unique:utilisateurs,cni'],
        ], [
            'telephone.regex' => Telephone::MESSAGE,
            'telephone.unique' => 'Ce numéro de téléphone est déjà associé à un compte.',
            'cni.regex' => 'Le numéro de CNI doit être composé de 10 à 13 chiffres.',
            'cni.unique' => 'Ce numéro de CNI est déjà associé à un compte.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $donnees = $validator->validated();

        // Numéro ajouté après le dépôt de la photo : on le compare au numéro lu.
        if (isset($donnees['cni']) && $utilisateur->numero_cni_detecte) {
            $donnees['numero_cni_concorde'] = $utilisateur->numero_cni_detecte === $donnees['cni'];
        }

        $utilisateur->update($donnees);

        return response()->json($utilisateur->fresh()->load('tribunal'));
    }
}
