<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhisperTranscriptionService
{
    private const ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    private const MODELE = 'whisper-1';

    public function transcrire(string $cheminAbsoluFichierAudio): ?string
    {
        if (! config('services.openai.api_key')) {
            Log::warning('WhisperTranscriptionService: OPENAI_API_KEY absente, transcription ignorée.');

            return null;
        }

        if (! is_file($cheminAbsoluFichierAudio)) {
            Log::warning("WhisperTranscriptionService: fichier introuvable ({$cheminAbsoluFichierAudio}).");

            return null;
        }

        try {
            $reponse = Http::withToken(config('services.openai.api_key'))
                ->timeout(120)
                ->attach('file', file_get_contents($cheminAbsoluFichierAudio), basename($cheminAbsoluFichierAudio))
                ->post(self::ENDPOINT, [
                    'model' => self::MODELE,
                    'language' => 'fr',
                ]);

            if (! $reponse->successful()) {
                Log::warning('WhisperTranscriptionService: échec API - '.$reponse->status().' '.$reponse->body());

                return null;
            }

            return $reponse->json('text');
        } catch (\Throwable $e) {
            Log::warning('WhisperTranscriptionService: exception - '.$e->getMessage());

            return null;
        }
    }
}
