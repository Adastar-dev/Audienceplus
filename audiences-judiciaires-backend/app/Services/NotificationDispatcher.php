<?php

namespace App\Services;

use App\Models\Convocation;
use App\Models\Notification;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcher
{
    public function envoyerConvocation(Convocation $convocation): void
    {
        $convocation->loadMissing(['utilisateur', 'audience.dossier']);
        $audience = $convocation->audience;

        $message = sprintf(
            'Convocation : vous êtes attendu(e) au tribunal le %s pour le dossier %s.',
            $audience->date_heure->translatedFormat('d/m/Y à H:i'),
            $audience->dossier->numero,
        );

        $this->envoyer($convocation->utilisateur, $convocation->canal, 'Convocation', $message);
    }

    public function envoyerRappel(Convocation $convocation): void
    {
        $convocation->loadMissing(['utilisateur', 'audience.dossier']);
        $audience = $convocation->audience;

        $message = sprintf(
            'Rappel : audience prévue le %s pour le dossier %s.',
            $audience->date_heure->translatedFormat('d/m/Y à H:i'),
            $audience->dossier->numero,
        );

        $this->envoyer($convocation->utilisateur, $convocation->canal, 'Rappel', $message);
    }

    public function envoyerLibre(Utilisateur $utilisateur, string $type, string $message): void
    {
        $this->envoyer($utilisateur, $utilisateur->telephone ? 'SMS' : 'EMAIL', $type, $message);
    }

    private function envoyer(Utilisateur $utilisateur, string $canal, string $type, string $message): void
    {
        Notification::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'type' => $type,
            'message' => $message,
            'lu' => false,
            'date_envoi' => now(),
        ]);

        try {
            match ($canal) {
                'SMS', 'APPEL_VOCAL' => $this->envoyerSms($utilisateur->telephone, $message),
                'EMAIL' => $this->envoyerEmail($utilisateur->email, $type, $message),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning("NotificationDispatcher: échec d'envoi {$canal} à l'utilisateur {$utilisateur->id_utilisateur} - ".$e->getMessage());
        }
    }

    // Envoi via l'API SMS Sénégal d'Orange (OAuth2 client_credentials + envoi
    // REST). Numéros stockés en base déjà au format international (+221...).
    private function envoyerSms(?string $telephone, string $message): void
    {
        if (! $telephone) {
            return;
        }

        $sender = config('services.orange.sender_address');
        $token = $this->obtenirTokenOrange();

        if (! $sender || ! $token) {
            return;
        }

        Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('https://api.orange.com/smsmessaging/v1/outbound/'.rawurlencode($sender).'/requests', [
                'outboundSMSMessageRequest' => [
                    'address' => 'tel:'.$telephone,
                    'senderAddress' => $sender,
                    'outboundSMSTextMessage' => ['message' => $message],
                ],
            ]);
    }

    // Le token OAuth2 Orange n'est valable qu'une heure : on le met en cache
    // pour éviter une authentification à chaque SMS.
    private function obtenirTokenOrange(): ?string
    {
        $clientId = config('services.orange.client_id');
        $clientSecret = config('services.orange.client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $token = Cache::get('orange_sms_token');
        if ($token) {
            return $token;
        }

        $reponse = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('https://api.orange.com/oauth/v3/token', ['grant_type' => 'client_credentials']);

        if (! $reponse->successful()) {
            return null;
        }

        $token = $reponse->json('access_token');
        Cache::put('orange_sms_token', $token, now()->addMinutes(55));

        return $token;
    }

    private function envoyerEmail(?string $email, string $sujet, string $message): void
    {
        if (! $email) {
            return;
        }

        Mail::raw($message, function ($mail) use ($email, $sujet) {
            $mail->to($email)->subject("Audiences judiciaires — {$sujet}");
        });
    }
}
