<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\PartieDossier;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avec' => 'required|integer|exists:utilisateurs,id_utilisateur',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $moi = $request->user();
        $autre = Utilisateur::find((int) $request->query('avec'));

        if (! $this->conversationAutorisee($moi, $autre)) {
            return response()->json([
                'errors' => ['avec' => ['Vous ne pouvez pas consulter cette conversation.']],
            ], 422);
        }

        $messages = Message::where(function ($q) use ($moi, $autre) {
            $q->where('id_expediteur', $moi->id_utilisateur)->where('id_destinataire', $autre->id_utilisateur);
        })->orWhere(function ($q) use ($moi, $autre) {
            $q->where('id_expediteur', $autre->id_utilisateur)->where('id_destinataire', $moi->id_utilisateur);
        })->orderBy('date_envoi')->get();

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_destinataire' => 'required|integer|exists:utilisateurs,id_utilisateur',
            'contenu' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $expediteur = $request->user();
        $destinataire = Utilisateur::find($request->id_destinataire);

        if (! $this->conversationAutorisee($expediteur, $destinataire)) {
            return response()->json([
                'errors' => ['id_destinataire' => ['Vous ne pouvez pas contacter cet utilisateur.']],
            ], 422);
        }

        $message = Message::create([
            'id_expediteur' => $expediteur->id_utilisateur,
            'id_destinataire' => $destinataire->id_utilisateur,
            'contenu' => $request->contenu,
            'lu' => false,
            'date_envoi' => now(),
        ]);

        return response()->json($message, 201);
    }

    // La messagerie interne couvre : la coordination interne du tribunal
    // (greffier, juge, procureur - toute paire entre ces trois rôles),
    // et avocat <-> justiciable, mais seulement s'ils sont déjà liés par
    // un dossier réel (l'avocat représente ce client) - jamais un avocat
    // et un justiciable pris au hasard. L'avocat peut en revanche contacter
    // librement le greffier (coordination sur ses dossiers), comme les
    // rôles internes entre eux.
    private function conversationAutorisee(?Utilisateur $a, ?Utilisateur $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        $roles = [$a->role, $b->role];
        sort($roles);

        $rolesInternes = ['GREFFIER', 'JUGE', 'PROCUREUR'];
        if (in_array($roles[0], $rolesInternes, true) && in_array($roles[1], $rolesInternes, true)) {
            return true;
        }

        if ($roles === ['AVOCAT', 'GREFFIER']) {
            return true;
        }

        if ($roles === ['AVOCAT', 'JUSTICIABLE']) {
            $idAvocat = $a->role === 'AVOCAT' ? $a->id_utilisateur : $b->id_utilisateur;
            $idJusticiable = $a->role === 'JUSTICIABLE' ? $a->id_utilisateur : $b->id_utilisateur;

            $idsPartiesJusticiable = PartieDossier::where('id_utilisateur', $idJusticiable)->pluck('id_partie');

            return PartieDossier::where('id_utilisateur', $idAvocat)
                ->whereIn('represente_id_partie', $idsPartiesJusticiable)
                ->exists();
        }

        return false;
    }
}