<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\LogActivite;
use App\Models\Notification;

// Archivage automatique : un dossier jugé est archivé quand toutes les parties
// (justiciables et avocats) ont accusé réception de la décision, que le délai
// de contestation est écoulé depuis le dernier accusé et que le PV n'a pas été
// contesté.
class ArchivageDossiers
{
    public const DELAI_JOURS = 15;

    public function archiverLesDossiersEchus(): int
    {
        $archives = 0;

        foreach (Dossier::where('statut', 'JUGE')->get() as $dossier) {
            if ($this->peutEtreArchive($dossier)) {
                $dossier->archiver();
                LogActivite::create(['action' => "Archivage automatique du dossier {$dossier->numero}"]);
                $archives++;
            }
        }

        return $archives;
    }

    public function peutEtreArchive(Dossier $dossier): bool
    {
        $audience = $dossier->audiences()
            ->where('type_decision', 'JUGEMENT')
            ->where('statut', 'CLOTUREE')
            ->latest('date_heure')
            ->first();

        if (! $audience) {
            return false;
        }

        $pv = $audience->procesVerbal;
        if ($pv && ($pv->statut === 'CONTESTE' || $pv->contestation_avocat)) {
            return false;
        }

        $parties = $dossier->utilisateursAConvoquer()->pluck('id_utilisateur');
        if ($parties->isEmpty()) {
            return false;
        }

        $accuses = Notification::where('id_audience', $audience->id_audience)
            ->where('type', 'Décision')
            ->whereIn('id_utilisateur', $parties)
            ->whereNotNull('date_accuse_reception')
            ->get();

        if ($parties->diff($accuses->pluck('id_utilisateur'))->isNotEmpty()) {
            return false;
        }

        return $accuses->max('date_accuse_reception')->lte(now()->subDays(self::DELAI_JOURS));
    }
}
