<?php

namespace App\Services;

use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\Notification;
use App\Models\Utilisateur;
use App\Support\Message;
use App\Support\Messages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcher
{
    public function envoyerConvocation(Convocation $convocation): void
    {
        $convocation->loadMissing(['utilisateur', 'audience.dossier']);
        $this->envoyerMessage($convocation->utilisateur, Messages::convocation($convocation->audience));
    }

    public function envoyerRappel(Convocation $convocation): void
    {
        $convocation->loadMissing(['utilisateur', 'audience.dossier']);
        $this->envoyerMessage($convocation->utilisateur, Messages::rappel($convocation->audience));
    }

    // Chaque message part par email et apparaît dans les notifications de la
    // plateforme.
    public function envoyerMessage(Utilisateur $utilisateur, Message $message): void
    {
        Notification::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'type' => $message->type,
            'message' => $message->notificationPlateforme ?? $message->texte,
            'lu' => false,
            'date_envoi' => now(),
            'id_audience' => $message->idAudience,
            'lien' => $message->lien,
        ]);

        if ($utilisateur->email) {
            $this->tenter($utilisateur, fn () => $this->envoyerEmail($utilisateur, $message));
        }
    }

    // Justiciable ou avocat sans photo complète de sa carte d'identité : une
    // notification (plateforme et email) l'invite à la déposer, sans doublon
    // tant que la précédente n'a pas été lue.
    public function rappelerIdentiteSiNecessaire(Utilisateur $utilisateur): void
    {
        if (! in_array($utilisateur->role, ['JUSTICIABLE', 'AVOCAT'], true) || $utilisateur->a_photo_cni) {
            return;
        }

        $dejaPrevenu = Notification::where('id_utilisateur', $utilisateur->id_utilisateur)
            ->where('type', 'Identité à compléter')
            ->where('lu', false)
            ->exists();

        if (! $dejaPrevenu) {
            $this->envoyerMessage($utilisateur, Messages::identiteACompleter());
        }
    }

    // Prévient les greffiers du tribunal du dossier ; si aucun greffier n'est
    // rattaché à ce tribunal, tous les greffiers, pour que la demande ne reste
    // pas sans réponse.
    public function notifierGreffiers(Dossier $dossier, Message $message): void
    {
        $greffiers = Utilisateur::where('role', 'GREFFIER')->where('id_tribunal', $dossier->id_tribunal)->get();

        if ($greffiers->isEmpty()) {
            $greffiers = Utilisateur::where('role', 'GREFFIER')->get();
        }

        foreach ($greffiers as $greffier) {
            $this->envoyerMessage($greffier, $message);
        }
    }

    private function tenter(Utilisateur $utilisateur, callable $envoi): void
    {
        try {
            $envoi();
        } catch (\Throwable $e) {
            Log::warning("NotificationDispatcher: échec d'envoi de l'email à l'utilisateur {$utilisateur->id_utilisateur} - ".$e->getMessage());
        }
    }

    private function envoyerEmail(Utilisateur $utilisateur, Message $message): void
    {
        $sujet = 'Audience+ — '.$message->type
            .(isset($message->details['Dossier']) ? ' — dossier '.$message->details['Dossier'] : '');

        Mail::raw($this->corpsEmail($utilisateur, $message), function ($mail) use ($utilisateur, $sujet) {
            $mail->to($utilisateur->email)->subject($sujet);
        });
    }

    private function corpsEmail(Utilisateur $utilisateur, Message $message): string
    {
        $lignes = ["Bonjour {$utilisateur->nom},", '', $message->texte];

        if ($message->details) {
            $lignes[] = '';
            foreach ($message->details as $libelle => $valeur) {
                $lignes[] = "• {$libelle} : {$valeur}";
            }
        }

        if ($message->action) {
            $lignes[] = '';
            $lignes[] = $message->action;
        }

        array_push(
            $lignes,
            '',
            'Accéder à votre espace Audience+ : '.config('app.frontend_url'),
            '',
            'Cordialement,',
            "L'équipe Audience+",
            '',
            '—',
            "Ce message vous est envoyé automatiquement par Audience+, la plateforme de gestion des audiences d'état civil. "
                ."Merci de ne pas y répondre : pour toute question, adressez-vous au greffe du tribunal.",
        );

        return implode("\n", $lignes);
    }
}
