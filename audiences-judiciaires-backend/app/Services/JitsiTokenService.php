<?php

namespace App\Services;

use App\Models\Audience;
use App\Models\Utilisateur;

// Jetons JWT pour le serveur Jitsi auto-heberge (voir docker-compose.yml).
// Prosody (AUTH_TYPE=jwt + module token_affiliation) lit context.user.moderator
// pour decider qui est modérateur, et jicofo a ENABLE_AUTO_OWNER=0 : le premier
// arrive ne devient plus modérateur, seul le jeton du juge le permet.
class JitsiTokenService
{
    private const DUREE_VALIDITE_SECONDES = 4 * 3600;

    public function estConfigure(): bool
    {
        return filled(config('services.jitsi.app_secret'));
    }

    public function domaine(): string
    {
        return $this->estConfigure() ? config('services.jitsi.domain') : 'meet.jit.si';
    }

    public function nomSalle(Audience $audience): string
    {
        return "aj-{$audience->room_name}";
    }

    public function genererJeton(Audience $audience, Utilisateur $utilisateur, bool $moderateur): ?string
    {
        if (! $this->estConfigure()) {
            return null;
        }

        $maintenant = time();

        return $this->encoder([
            'aud' => config('services.jitsi.audience'),
            'iss' => config('services.jitsi.app_id'),
            'sub' => config('services.jitsi.xmpp_domain'),
            'room' => $this->nomSalle($audience),
            'nbf' => $maintenant - 10,
            'exp' => $maintenant + self::DUREE_VALIDITE_SECONDES,
            'context' => [
                'user' => [
                    'id' => (string) $utilisateur->id_utilisateur,
                    'name' => $utilisateur->nom,
                    'email' => $utilisateur->email,
                    'moderator' => $moderateur,
                ],
            ],
        ]);
    }

    private function encoder(array $payload): string
    {
        $segments = [
            $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), config('services.jitsi.app_secret'), true);
        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }
}
