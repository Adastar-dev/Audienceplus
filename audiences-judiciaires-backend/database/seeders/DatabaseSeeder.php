<?php

namespace Database\Seeders;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\SalleVirtuelle;
use App\Models\Tribunal;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tribunalDakar = Tribunal::create([
            'nom' => 'Tribunal de Grande Instance de Dakar',
            'ville' => 'Dakar',
        ]);

        Tribunal::create(['nom' => 'Tribunal de Thiès', 'ville' => 'Thiès']);
        Tribunal::create(['nom' => 'Tribunal de Saint-Louis', 'ville' => 'Saint-Louis']);

        SalleVirtuelle::create([
            'id_tribunal' => $tribunalDakar->id_tribunal,
            'lien_jitsi' => 'meet.jit.si/aj-dakar-salle1',
            'code_acces' => 'DKR-SALLE1',
        ]);
        SalleVirtuelle::create([
            'id_tribunal' => $tribunalDakar->id_tribunal,
            'lien_jitsi' => 'meet.jit.si/aj-dakar-salle2',
            'code_acces' => 'DKR-SALLE2',
        ]);

        $mdpDemo = Hash::make('password');

        $juge = Utilisateur::create([
            'nom' => 'Fatou Diallo', 'email' => 'f.diallo@justice.sn',
            'mot_de_passe' => $mdpDemo, 'role' => 'JUGE',
            'id_tribunal' => $tribunalDakar->id_tribunal, 'identite_verifiee' => true,
        ]);

        $greffier = Utilisateur::create([
            'nom' => 'Moussa Sy', 'email' => 'm.sy@justice.sn',
            'mot_de_passe' => $mdpDemo, 'role' => 'GREFFIER',
            'id_tribunal' => $tribunalDakar->id_tribunal, 'identite_verifiee' => true,
        ]);

        Utilisateur::create([
            'nom' => 'Cheikh Ba', 'email' => 'c.ba@justice.sn',
            'mot_de_passe' => $mdpDemo, 'role' => 'PROCUREUR',
            'id_tribunal' => $tribunalDakar->id_tribunal, 'identite_verifiee' => true,
        ]);

        $avocat = Utilisateur::create([
            'nom' => 'Awa Fall', 'email' => 'awa.fall@barreau.sn',
            'mot_de_passe' => $mdpDemo, 'role' => 'AVOCAT',
            'numero_barreau' => 'BSN-2019-0231', 'identite_verifiee' => true,
        ]);

        $justiciable = Utilisateur::create([
            'nom' => 'Aïda Ndiaye', 'email' => 'aida.ndiaye@mail.sn',
            'mot_de_passe' => $mdpDemo, 'role' => 'JUSTICIABLE',
            'cni' => '1234567890123', 'identite_verifiee' => true,
        ]);

        Utilisateur::create([
            'nom' => 'Admin Système', 'email' => 'admin@justice.sn',
            'mot_de_passe' => $mdpDemo, 'role' => 'ADMINISTRATEUR', 'identite_verifiee' => true,
        ]);

        $dossier1 = Dossier::create([
            'numero' => 'TRB-DKR-2026-0142', 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'Ndiaye c. Sarr', 'id_tribunal' => $tribunalDakar->id_tribunal,
            'date_creation' => '2026-07-02',
        ]);

        Dossier::create([
            'numero' => 'TRB-DKR-2026-0143', 'type' => 'ADOPTION', 'statut' => 'RENVOYE',
            'parties' => 'Famille Diop', 'id_tribunal' => $tribunalDakar->id_tribunal,
            'date_creation' => '2026-07-05',
        ]);

        $audience = Audience::create([
            'id_dossier' => $dossier1->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => '2026-09-03 09:00:00', 'statut' => 'PROGRAMMEE',
        ]);

        Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE',
        ]);
        Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $avocat->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE',
        ]);
    }
}
