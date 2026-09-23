<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\PartieDossier;
use App\Models\ProcesVerbal;
use App\Services\WhisperTranscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProcesVerbalController extends Controller
{
    public function __construct(private WhisperTranscriptionService $whisper)
    {
    }

    public function show(Audience $audience)
    {
        $pv = $audience->procesVerbal;

        if (! $pv) {
            return response()->json(['message' => 'Aucun PV pour cette audience.'], 404);
        }

        return response()->json($pv);
    }

    public function storeOrUpdate(Request $request, Audience $audience)
    {
        $validator = Validator::make($request->all(), [
            'contenu' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pv = ProcesVerbal::updateOrCreate(
            ['id_audience' => $audience->id_audience],
            ['contenu' => $request->contenu, 'statut' => 'EN_COURS']
        );

        return response()->json($pv);
    }

    public function transmettre(Audience $audience)
    {
        $pv = $audience->procesVerbal;

        if (! $pv) {
            return response()->json(['message' => 'Aucun PV rédigé pour cette audience.'], 404);
        }

        $pv->update(['statut' => 'EN_VALIDATION', 'commentaire_rejet' => null]);

        return response()->json($pv);
    }

    public function valider(Audience $audience)
    {
        $pv = $audience->procesVerbal;

        if (! $pv) {
            return response()->json(['message' => 'Aucun PV rédigé pour cette audience.'], 404);
        }

        $pv->update(['statut' => 'CLOTURE', 'date_validation' => now()]);

        return response()->json($pv);
    }

    public function rejeter(Request $request, Audience $audience)
    {
        $validator = Validator::make($request->all(), [
            'commentaire' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pv = $audience->procesVerbal;

        if (! $pv) {
            return response()->json(['message' => 'Aucun PV rédigé pour cette audience.'], 404);
        }

        $pv->update(['statut' => 'EN_COURS', 'commentaire_rejet' => $request->commentaire]);

        return response()->json($pv);
    }

    // Le procureur a un rôle consultatif (comme son avis sur le dossier) :
    // il peut donner son avis sur le PV quel que soit son statut, sans que
    // cela bloque ou modifie la validation par le juge.
    public function donnerAvis(Request $request, Audience $audience)
    {
        $validator = Validator::make($request->all(), [
            'avis' => 'required|string|max:4000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pv = $audience->procesVerbal;

        if (! $pv) {
            return response()->json(['message' => 'Aucun PV rédigé pour cette audience.'], 404);
        }

        $pv->update([
            'avis_procureur' => $request->avis,
            'avis_procureur_par' => $request->user()->id_utilisateur,
            'avis_procureur_date' => now(),
        ]);

        return response()->json($pv->load('procureurQuiADonneAvis'));
    }

    // Un avocat ne peut contester que le PV d'un dossier où il représente
    // une partie, et seulement une fois que le juge l'a validé (CLOTURE) -
    // avant ça, le PV est encore en rédaction/relecture, il n'y a rien à
    // contester formellement.
    public function contester(Request $request, Audience $audience)
    {
        $validator = Validator::make($request->all(), [
            'commentaire' => 'required|string|max:4000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pv = $audience->procesVerbal;

        if (! $pv) {
            return response()->json(['message' => 'Aucun PV rédigé pour cette audience.'], 404);
        }

        if ($pv->statut !== 'CLOTURE') {
            return response()->json([
                'message' => 'Seul un procès-verbal déjà validé par le juge peut être contesté.',
            ], 422);
        }

        $estAvocatDuDossier = $audience->dossier && PartieDossier::where('id_dossier', $audience->dossier->id_dossier)
            ->where('id_utilisateur', $request->user()->id_utilisateur)
            ->whereNotNull('represente_id_partie')
            ->exists();

        if (! $estAvocatDuDossier) {
            return response()->json([
                'message' => "Vous n'êtes pas l'avocat de ce dossier.",
            ], 403);
        }

        $pv->update([
            'statut' => 'CONTESTE',
            'contestation_avocat' => $request->commentaire,
            'conteste_par' => $request->user()->id_utilisateur,
            'date_contestation' => now(),
        ]);

        return response()->json($pv->load('avocatQuiConteste'));
    }

    public function transcrire(Request $request, Audience $audience)
    {
        $validator = Validator::make($request->all(), [
            'audio' => 'required|file|mimes:mp3,wav,m4a,ogg,webm,mp4|max:25600',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mime = $request->file('audio')->getMimeType();
        if (! str_starts_with($mime, 'audio/') && ! str_starts_with($mime, 'video/')) {
            return response()->json(['errors' => ['audio' => ["Le fichier n'est pas un enregistrement audio valide."]]], 422);
        }

        $chemin = $request->file('audio')->store('audios-audiences', 'local');
        $cheminAbsolu = Storage::disk('local')->path($chemin);

        $texte = $this->whisper->transcrire($cheminAbsolu);

        $pv = ProcesVerbal::updateOrCreate(
            ['id_audience' => $audience->id_audience],
            ['audio_path' => $chemin]
        );

        if ($texte !== null) {
            $pv->update(['transcription_brute' => $texte]);
        }

        return response()->json([
            'pv' => $pv,
            'transcription_disponible' => $texte !== null,
            'message' => $texte === null
                ? "L'audio a été enregistré, mais la transcription automatique a échoué ou n'est pas configurée. Vous pouvez rédiger le PV manuellement."
                : null,
        ]);
    }
}