<?php

namespace App\Support;

// Contenu d'une notification (email et notification sur la plateforme) :
// - $texte : phrase principale ;
// - $details : lignes « Libellé : valeur » ajoutées à l'email ;
// - $action : ce que le destinataire doit faire, en fin d'email ;
// - $notificationPlateforme : texte affiché sur la plateforme s'il doit
//   différer du texte (par exemple, jamais de code OTP en clair).
class Message
{
    public function __construct(
        public string $type,
        public string $texte,
        public array $details = [],
        public ?string $action = null,
        public ?string $notificationPlateforme = null,
        // Audience concernée (décision) : sert à suivre les accusés de réception.
        public ?int $idAudience = null,
        // Page de la plateforme où agir : la notification devient cliquable.
        public ?string $lien = null,
    ) {
    }
}
