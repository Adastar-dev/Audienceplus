<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\Notification;
use App\Models\PartieDossier;
use App\Models\ProcesVerbal;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivageEtRappelsTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $juge;
    private Utilisateur $justiciable;
    private Utilisateur $avocat;

    private function dossierJuge(): Dossier
    {
        $this->juge = $this->creerUtilisateur('JUGE');
        $this->justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $this->avocat = $this->creerUtilisateur('AVOCAT');

        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);
        $partie = PartieDossier::create([
            'id_dossier' => $dossier->id_dossier, 'id_utilisateur' => $this->justiciable->id_utilisateur, 'role_partie' => 'DEMANDEUR',
        ]);
        PartieDossier::create([
            'id_dossier' => $dossier->id_dossier, 'id_utilisateur' => $this->avocat->id_utilisateur,
            'role_partie' => 'DEMANDEUR', 'represente_id_partie' => $partie->id_partie,
        ]);

        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'id_juge' => $this->juge->id_utilisateur, 'date_heure' => now(),
            'mode' => 'PRESENTIEL', 'statut' => 'EN_COURS', 'type_decision' => 'JUGEMENT',
        ]);

        // La clôture notifie la décision aux parties.
        $this->actingAs($this->juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/fermer")
            ->assertOk();

        return $dossier->fresh();
    }

    private function accuserReception(Utilisateur $utilisateur): void
    {
        $notification = Notification::where('id_utilisateur', $utilisateur->id_utilisateur)->where('type', 'Décision')->firstOrFail();

        $this->actingAs($utilisateur, 'sanctum')
            ->postJson("/api/notifications/{$notification->id_notification}/accuser-reception")
            ->assertOk();
    }

    public function test_la_notification_de_decision_est_liee_a_laudience(): void
    {
        $dossier = $this->dossierJuge();

        $this->assertEquals('JUGE', $dossier->statut);
        $this->assertEquals(2, Notification::where('type', 'Décision')->whereNotNull('id_audience')->count());
    }

    public function test_le_dossier_est_archive_15_jours_apres_le_dernier_accuse_de_reception(): void
    {
        $dossier = $this->dossierJuge();
        $this->accuserReception($this->justiciable);
        $this->accuserReception($this->avocat);

        $this->artisan('dossiers:archiver')->assertSuccessful();
        $this->assertEquals('JUGE', $dossier->fresh()->statut);

        $this->travel(16)->days();
        $this->artisan('dossiers:archiver')->assertSuccessful();
        $this->assertEquals('ARCHIVE', $dossier->fresh()->statut);
        $this->assertNotNull($dossier->fresh()->date_archivage);
    }

    public function test_pas_darchivage_tant_quune_partie_na_pas_accuse_reception(): void
    {
        $dossier = $this->dossierJuge();
        $this->accuserReception($this->justiciable);

        $this->travel(30)->days();
        $this->artisan('dossiers:archiver');

        $this->assertEquals('JUGE', $dossier->fresh()->statut);
    }

    public function test_pas_darchivage_si_le_pv_est_conteste(): void
    {
        $dossier = $this->dossierJuge();
        ProcesVerbal::create([
            'id_audience' => $dossier->audiences()->first()->id_audience, 'contenu' => 'PV',
            'statut' => 'CONTESTE', 'contestation_avocat' => 'Erreur sur les faits.',
        ]);
        $this->accuserReception($this->justiciable);
        $this->accuserReception($this->avocat);

        $this->travel(30)->days();
        $this->artisan('dossiers:archiver');

        $this->assertEquals('JUGE', $dossier->fresh()->statut);
    }

    public function test_le_juge_archive_un_dossier_juge_qui_devient_non_modifiable(): void
    {
        $dossier = $this->dossierJuge();

        $this->actingAs($this->juge, 'sanctum')
            ->postJson("/api/dossiers/{$dossier->id_dossier}/archiver")
            ->assertOk()
            ->assertJsonPath('statut', 'ARCHIVE');

        $greffier = $this->creerUtilisateur('GREFFIER');
        $this->actingAs($greffier, 'sanctum')
            ->postJson('/api/audiences', ['id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addWeek()->toDateTimeString()])
            ->assertStatus(409);
        $this->actingAs($this->juge, 'sanctum')
            ->patchJson("/api/dossiers/{$dossier->id_dossier}", ['statut' => 'EN_COURS'])
            ->assertStatus(409);
    }

    public function test_un_juge_etranger_au_dossier_ne_peut_pas_larchiver(): void
    {
        $dossier = $this->dossierJuge();

        $this->actingAs($this->creerUtilisateur('JUGE'), 'sanctum')
            ->postJson("/api/dossiers/{$dossier->id_dossier}/archiver")
            ->assertStatus(403);
    }

    public function test_un_dossier_non_juge_ne_peut_pas_etre_archive(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);
        Audience::create([
            'id_dossier' => $dossier->id_dossier, 'id_juge' => $juge->id_utilisateur, 'date_heure' => now()->addDay(),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/dossiers/{$dossier->id_dossier}/archiver")
            ->assertStatus(409);
    }

    public function test_rappels_48h_et_2h_envoyes_une_seule_fois(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'id_juge' => $juge->id_utilisateur, 'date_heure' => now()->addHours(40),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);
        $audience->forceFill(['created_at' => now()->subDays(5)])->save();
        Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE', 'date_envoi' => now()->subDays(5),
        ]);

        $this->artisan('audiences:rappels')->assertSuccessful();
        $this->artisan('audiences:rappels')->assertSuccessful();

        $this->assertEquals(1, Notification::where('id_utilisateur', $justiciable->id_utilisateur)->where('type', 'Rappel')->count());
        $this->assertEquals(1, Notification::where('id_utilisateur', $juge->id_utilisateur)->where('type', 'Rappel')->count());
        $this->assertNotNull($audience->fresh()->rappel_48h_le);
        $this->assertNull($audience->fresh()->rappel_2h_le);

        $this->travel(39)->hours();
        $this->artisan('audiences:rappels')->assertSuccessful();

        $this->assertEquals(2, Notification::where('id_utilisateur', $justiciable->id_utilisateur)->where('type', 'Rappel')->count());
        $this->assertNotNull($audience->fresh()->rappel_2h_le);
    }

    public function test_pas_de_rappel_48h_pour_une_audience_qui_vient_detre_programmee(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addHours(30),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);
        Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE', 'date_envoi' => now(),
        ]);

        $this->artisan('audiences:rappels');

        $this->assertEquals(0, Notification::where('type', 'Rappel')->count());
        $this->assertNotNull($audience->fresh()->rappel_48h_le);
    }
}
