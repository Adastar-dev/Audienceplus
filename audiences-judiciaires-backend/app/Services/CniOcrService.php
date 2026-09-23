<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Extrait le numero visible sur une photo de CNI via un modele OpenAI a
// vision (reutilise OPENAI_API_KEY, deja configure pour
// WhisperTranscriptionService - evite d'ajouter un quatrieme service externe
// pour ce seul besoin). Meme principe que les autres services externes du
// projet : un echec est journalise et renvoie null plutot que de faire
// planter le flux appelant.
class CniOcrService
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const MODELE = 'gpt-4o-mini';

    public function extraireNumero(string $cheminPhotoCni): ?string
    {
        if (! config('services.openai.api_key')) {
            Log::warning('CniOcrService: OPENAI_API_KEY absente, extraction ignorée.');

            return null;
        }

        if (! is_file($cheminPhotoCni)) {
            Log::warning("CniOcrService: fichier introuvable ({$cheminPhotoCni}).");

            return null;
        }

        try {
            $mime = mime_content_type($cheminPhotoCni) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($cheminPhotoCni));

            $reponse = Http::withToken(config('services.openai.api_key'))
                ->timeout(30)
                ->post(self::ENDPOINT, [
                    'model' => self::MODELE,
                    'max_tokens' => 30,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "Extrait uniquement le numéro d'identification (CNI) visible sur "
                                    ."cette image, sans aucun autre texte ni ponctuation. Si aucun numéro "
                                    ."n'est lisible, réponds exactement AUCUN.",
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => "data:{$mime};base64,{$base64}"],
                            ],
                        ],
                    ]],
                ]);

            if (! $reponse->successful()) {
                Log::warning('CniOcrService: échec API - '.$reponse->status().' '.$reponse->body());

                return null;
            }

            $texte = trim((string) $reponse->json('choices.0.message.content'));
            $chiffres = preg_replace('/\D/', '', $texte);

            return $chiffres !== '' ? $chiffres : null;
        } catch (\Throwable $e) {
            Log::warning('CniOcrService: exception - '.$e->getMessage());

            return null;
        }
    }
}
