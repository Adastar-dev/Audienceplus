<?php

namespace App\Services;

use App\Models\ParticipationAudience;
use Illuminate\Support\Facades\Hash;
use App\Support\Messages;

// Vérification d'identité avant l'entrée en salle d'attente virtuelle : un code
// à 6 chiffres envoyé par email, à saisir pour confirmer l'identité. Remplace la comparaison faciale (Face++).
class OtpService
{
    private const DUREE_VALIDITE_MINUTES = 5;

    private const TENTATIVES_MAX = 5;

    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    // Renvoie false si le compte n'a pas d'adresse email.
    public function envoyer(ParticipationAudience $participation): bool
    {
        $utilisateur = $participation->utilisateur;

        if (! $utilisateur->email) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $participation->update([
            'otp_code_hash' => Hash::make($code),
            'otp_expire_a' => now()->addMinutes(self::DUREE_VALIDITE_MINUTES),
            'otp_tentatives' => 0,
            'identite_confirmee_otp' => false,
        ]);

        // Jamais de code en clair dans les notifications de la plateforme.
        $this->notifications->envoyerMessage(
            $utilisateur,
            Messages::codeVerification($code, self::DUREE_VALIDITE_MINUTES),
        );

        return true;
    }

    // Retourne 'ok', 'invalide', 'expire', 'trop_de_tentatives' ou 'aucun_code'.
    public function verifier(ParticipationAudience $participation, string $code): string
    {
        if (! $participation->otp_code_hash || ! $participation->otp_expire_a) {
            return 'aucun_code';
        }

        if ($participation->otp_tentatives >= self::TENTATIVES_MAX) {
            return 'trop_de_tentatives';
        }

        if ($participation->otp_expire_a->isPast()) {
            return 'expire';
        }

        if (! Hash::check($code, $participation->otp_code_hash)) {
            $participation->increment('otp_tentatives');

            return 'invalide';
        }

        $participation->update([
            'identite_confirmee_otp' => true,
            'otp_code_hash' => null,
            'otp_expire_a' => null,
        ]);

        return 'ok';
    }
}
