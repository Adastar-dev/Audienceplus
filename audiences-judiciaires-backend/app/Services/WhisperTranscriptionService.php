<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Transcription d'un enregistrement d'audience avec le modèle Whisper, via le
// fournisseur d'IA configuré (Groq par défaut, voir config/services.php).
// Un échec est journalisé et renvoie null : le greffier rédige alors le PV
// manuellement.
class WhisperTranscriptionService
{
    public function transcrire(string $cheminAbsoluFichierAudio): ?string
    {
        $cle = config('services.ia.api_key');

        if (! $cle) {
            Log::warning('WhisperTranscriptionService: IA_API_KEY absente, transcription ignorée.');

            return null;
        }

        if (! is_file($cheminAbsoluFichierAudio)) {
            Log::warning("WhisperTranscriptionService: fichier introuvable ({$cheminAbsoluFichierAudio}).");

            return null;
        }

        try {
            $reponse = Http::withToken($cle)
                ->timeout(120)
                ->attach('file', file_get_contents($cheminAbsoluFichierAudio), basename($cheminAbsoluFichierAudio))
                ->post(config('services.ia.base_url').'/audio/transcriptions', [
                    'model' => config('services.ia.modele_transcription'),
                    'language' => 'fr',
                ]);

            if (! $reponse->successful()) {
                Log::warning('WhisperTranscriptionService: échec API - '.$reponse->status().' '.mb_substr($reponse->body(), 0, 500));

                return null;
            }

            return $reponse->json('text');
        } catch (\Throwable $e) {
            Log::warning('WhisperTranscriptionService: exception - '.$e->getMessage());

            return null;
        }
    }
}
