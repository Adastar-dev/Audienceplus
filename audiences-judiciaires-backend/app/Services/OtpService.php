<?php

namespace App\Services;

use App\Models\ParticipationAudience;
use Illuminate\Support\Facades\Hash;

// Remplace la comparaison faciale (Face++, voir FaceComparisonService retire) comme
// verification d'identite avant l'entree en salle d'attente virtuelle : un code a 6
// chiffres envoye par SMS au numero du compte, a saisir pour confirmer l'identite.
class OtpService
{
    private const DUREE_VALIDITE_MINUTES = 5;

    private const TENTATIVES_MAX = 5;

    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    public function envoyer(ParticipationAudience $participation): bool
    {
        $utilisateur = $participation->utilisateur;

        if (! $utilisateur->telephone) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $participation->update([
            'otp_code_hash' => Hash::make($code),
            'otp_expire_a' => now()->addMinutes(self::DUREE_VALIDITE_MINUTES),
            'otp_tentatives' => 0,
            'identite_confirmee_otp' => false,
        ]);

        $this->notifications->envoyerLibre(
            $utilisateur,
            'Code de vérification',
            "Votre code d'accès à l'audience : {$code} (valable ".self::DUREE_VALIDITE_MINUTES.' minutes).',
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
