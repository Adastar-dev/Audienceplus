<?php

namespace App\Http\Controllers;

use App\Models\LogActivite;
use App\Models\Utilisateur;
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
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'email' => 'nullable|email|unique:utilisateurs,email',
            'telephone' => 'required|string|max:30|unique:utilisateurs,telephone',
            'cni' => ['required', 'string', 'regex:/^[0-9]{10,13}$/', 'unique:utilisateurs,cni'],
            'mot_de_passe' => 'required|string|min:8',
            'role' => 'required|in:JUSTICIABLE,AVOCAT',
            'numero_barreau' => 'required_if:role,AVOCAT|nullable|string|max:50',
        ], [
            'cni.regex' => "Le numéro de CNI doit être composé de 10 à 13 chiffres.",
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

        $token = $utilisateur->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $utilisateur], 201);
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

        $utilisateur = Utilisateur::where('email', $request->identifiant)
            ->orWhere('telephone', $request->identifiant)
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

        $utilisateur->update([
            'tentatives_echouees' => $tentatives,
            'bloque_jusqu_a' => $tentatives >= self::TENTATIVES_MAX
                ? now()->addMinutes(self::BLOCAGE_MINUTES)
                : null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function utilisateurConnecte(Request $request)
    {
        return response()->json($request->user());
    }
}