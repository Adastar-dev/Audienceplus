<?php

namespace App\Services;

use App\Models\Audience;
use App\Models\Dossier;
use App\Models\Utilisateur;
use App\Support\Messages;

// Rappels envoyés automatiquement (tâche planifiée) aux parties convoquées, au
// juge et au procureur : 48 heures puis 2 heures avant l'audience.
class RappelsAudiences
{
    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    public function envoyer(): int
    {
        return $this->envoyerRappels('rappel_48h_le', 48) + $this->envoyerRappels('rappel_2h_le', 2);
    }

    private function envoyerRappels(string $colonne, int $heures): int
    {
        $audiences = Audience::with('dossier')
            ->where('statut', 'PROGRAMMEE')
            ->whereNull($colonne)
            ->whereBetween('date_heure', [now(), now()->addHours($heures)])
            ->get();

        $envoyes = 0;

        foreach ($audiences as $audience) {
            // Audience programmée il y a peu : la convocation vient d'être
            // envoyée, le rappel à 48 heures ferait doublon.
            $recente = $heures === 48 && $audience->created_at?->gt(now()->subHours(12));

            if (! $recente) {
                $this->prevenir($audience, $heures);
                $envoyes++;
            }

            $audience->update([$colonne => now()]);
        }

        return $envoyes;
    }

    private function prevenir(Audience $audience, int $heures): void
    {
        $parties = $audience->convocations()->with('utilisateur')->get()->pluck('utilisateur')->filter();
        $messageParties = Messages::rappelAvantAudience($audience, $heures, false);

        foreach ($parties->unique('id_utilisateur') as $utilisateur) {
            $this->notifications->envoyerMessage($utilisateur, $messageParties);
        }

        $magistrats = array_filter([
            $audience->id_juge,
            Dossier::communiqueAuParquet($audience->dossier->type) ? $audience->dossier->id_procureur : null,
        ]);
        $messageMagistrats = Messages::rappelAvantAudience($audience, $heures, true);

        foreach (Utilisateur::whereIn('id_utilisateur', $magistrats)->get() as $magistrat) {
            $this->notifications->envoyerMessage($magistrat, $messageMagistrats);
        }
    }
}
