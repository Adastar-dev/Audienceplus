<?php

use App\Services\ArchivageDossiers;
use App\Services\RappelsAudiences;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('audiences:rappels', function (RappelsAudiences $rappels) {
    $this->info($rappels->envoyer().' rappel(s) envoyé(s).');
})->purpose("Envoie les rappels 48 h et 2 h avant chaque audience programmée");

Artisan::command('dossiers:archiver', function (ArchivageDossiers $archivage) {
    $this->info($archivage->archiverLesDossiersEchus().' dossier(s) archivé(s).');
})->purpose('Archive les dossiers jugés dont la décision a été reçue par toutes les parties sans contestation');

// Exécutées par le service « scheduler » de docker-compose (php artisan schedule:work).
Schedule::command('audiences:rappels')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('dossiers:archiver')->dailyAt('02:00');
