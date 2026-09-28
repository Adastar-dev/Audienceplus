<?php

namespace App\Http\Controllers;

use App\Models\LogActivite;
use App\Services\NotificationDispatcher;
use App\Models\Utilisateur;
use App\Services\CniOcrService;
use App\Support\Telephone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private const TENTATIVES_MAX = 5;
    private const BLOCAGE_MINUTES = 15;

    // Format CNI supposé (10 à 13 chiffres, numéro d'identification national
    // sénégalais) — à ajuster si le format officiel exact diffère.
    public function inscription(Request $request)
    {
        $request->merge(['telephone' => Telephone::normaliser($request->telephone)]);

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            // Obligatoire : convocations, notifications et code de vérification
            // partent par email.
            'email' => 'required|email|unique:utilisateurs,email',
            'telephone' => ['required', 'string', Telephone::REGLE, 'unique:utilisateurs,telephone'],
            'cni' => ['required', 'string', 'regex:/^[0-9]{10,13}$/', 'unique:utilisateurs,cni'],
            'mot_de_passe' => 'required|string|min:8',
            'role' => 'required|in:JUSTICIABLE,AVOCAT',
            'numero_barreau' => 'required_if:role,AVOCAT|nullable|string|max:50',
            'cni_photo' => ['required', ...CniOcrService::REGLES_PHOTO],
            'cni_verso' => ['required', ...CniOcrService::REGLES_PHOTO],
        ], [
            'cni_photo.required' => 'La photo du recto de la carte d\'identité est obligatoire.',
            'cni_verso.required' => 'La photo du verso de la carte d\'identité est obligatoire.',
            'cni.regex' => "Le numéro de CNI doit être composé de 10 à 13 chiffres.",
            'telephone.regex' => Telephone::MESSAGE,
            'telephone.unique' => 'Ce numéro de téléphone est déjà associé à un compte.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $utilisateur = Utilisateur::create([
            'nom' => $request->nom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'cni' => $request->cni,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'role' => $request->role,
            'numero_barreau' => $request->numero_barreau,
            'identite_verifiee' => false,
        ]);

        if (! app(CniOcrService::class)->enregistrerPhotosCompte($utilisateur, $request->file('cni_photo'), $request->file('cni_verso'))) {
            $utilisateur->delete();

            return response()->json(['errors' => ['cni_photo' => ["Le fichier n'est pas une image valide."]]], 422);
        }

        $token = $utilisateur->createToken('auth_token')->plainTextToken;

        LogActivite::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'action' => 'Inscription',
            'adresse_ip' => $request->ip(),
        ]);

        return response()->json(['token' => $token, 'user' => $utilisateur->fresh()], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifiant' => 'required|string',
            'mot_de_passe' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // L'identifiant peut être un email ou un téléphone saisi librement
        // (77 000 00 00, +221 77..., 0022177...).
        $telephone = str_contains($request->identifiant, '@') ? null : Telephone::normaliser($request->identifiant);

        $utilisateur = Utilisateur::where('email', $request->identifiant)
            ->when($telephone, fn ($q) => $q->orWhere('telephone', $telephone))
            ->first();

        if ($utilisateur && $utilisateur->estBloque()) {
            return response()->json([
                'message' => 'Compte temporairement bloqué suite à plusieurs échecs de connexion. Réessayez plus tard.',
            ], 423);
        }

        if (! $utilisateur || ! Hash::check($request->mot_de_passe, $utilisateur->mot_de_passe)) {
            if ($utilisateur) {
                $this->enregistrerEchec($utilisateur);
            }

            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        if ($utilisateur->tentatives_echouees > 0 || $utilisateur->bloque_jusqu_a) {
            $utilisateur->update(['tentatives_echouees' => 0, 'bloque_jusqu_a' => null]);
        }

        $token = $utilisateur->createToken('auth_token')->plainTextToken;

        app(NotificationDispatcher::class)->rappelerIdentiteSiNecessaire($utilisateur);

        LogActivite::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'action' => 'Connexion',
            'adresse_ip' => $request->ip(),
        ]);

        return response()->json(['token' => $token, 'user' => $utilisateur]);
    }

    private function enregistrerEchec(Utilisateur $utilisateur): void
    {
        $tentatives = $utilisateur->tentatives_echouees + 1;

        $bloque = $tentatives >= self::TENTATIVES_MAX;

        $utilisateur->update([
            'tentatives_echouees' => $tentatives,
            'bloque_jusqu_a' => $bloque ? now()->addMinutes(self::BLOCAGE_MINUTES) : null,
        ]);

        LogActivite::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'action' => $bloque ? 'Échec de connexion - compte bloqué temporairement' : 'Échec de connexion',
            'adresse_ip' => request()->ip(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    // Informations du compte (page « Mon compte », en lecture seule).
    public function utilisateurConnecte(Request $request)
    {
        return response()->json($request->user()->load('tribunal'));
    }
}