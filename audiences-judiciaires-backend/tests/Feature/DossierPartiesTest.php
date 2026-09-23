<?php

namespace Tests\Feature;

use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DossierPartiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_dossier_avec_demandeur_et_avocat_cree_les_liaisons(): void
    {
        Mail::fake();
        Http::fake();

        $greffier = $this->creerUtilisateur('GREFFIER');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221770000001']);
        $avocat = $this->creerUtilisateur('AVOCAT');
        $tribunal = $this->tribunal();

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/dossiers', [
            'type' => 'DIVORCE',
            'id_tribunal' => $tribunal->id_tribunal,
            'demandeur' => 'Aïda Ndiaye',
            'defendeur' => 'Un Autre',
            'id_demandeur_utilisateur' => $justiciable->id_utilisateur,
            'id_demandeur_avocat' => $avocat->id_utilisateur,
        ]);

        $reponse->assertStatus(201);
        $this->assertDatabaseHas('parties_dossier', [
            'id_dossier' => $reponse->json('id_dossier'),
            'id_utilisateur' => $justiciable->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);
        $this->assertDatabaseHas('parties_dossier', [
            'id_dossier' => $reponse->json('id_dossier'),
            'id_utilisateur' => $avocat->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);
    }

    public function test_programmer_une_audience_convoque_automatiquement_les_parties_liees(): void
    {
        Mail::fake();
        Http::fake();

        $greffier = $this->creerUtilisateur('GREFFIER');
        $juge = $this->creerUtilisateur('JUGE');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221770000001']);
        $avocat = $this->creerUtilisateur('AVOCAT');
        $tribunal = $this->tribunal();

        $dossier = $this->actingAs($greffier, 'sanctum')->postJson('/api/dossiers', [
            'type' => 'DIVORCE',
            'id_tribunal' => $tribunal->id_tribunal,
            'demandeur' => 'Aïda Ndiaye',
            'defendeur' => 'Un Autre',
            'id_demandeur_utilisateur' => $justiciable->id_utilisateur,
            'id_demandeur_avocat' => $avocat->id_utilisateur,
        ])->json();

        $reponseAudience = $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $dossier['id_dossier'],
            'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->addDays(3)->toDateTimeString(),
            'mode' => 'PRESENTIEL',
        ]);

        $reponseAudience->assertStatus(201);
        $idAudience = $reponseAudience->json('id_audience');

        $this->assertEquals(2, Convocation::where('id_audience', $idAudience)->count());
        $this->assertDatabaseHas('convocations', [
            'id_audience' => $idAudience, 'id_utilisateur' => $justiciable->id_utilisateur,
        ]);
        $this->assertDatabaseHas('convocations', [
            'id_audience' => $idAudience, 'id_utilisateur' => $avocat->id_utilisateur,
        ]);

        $this->assertGreaterThanOrEqual(2, Notification::whereIn(
            'id_utilisateur', [$justiciable->id_utilisateur, $avocat->id_utilisateur]
        )->count());
    }

    public function test_un_dossier_sans_partie_liee_ne_convoque_personne(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $juge = $this->creerUtilisateur('JUGE');
        $tribunal = $this->tribunal();

        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-0002', 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);

        $reponseAudience = $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $dossier->id_dossier,
            'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->addDays(3)->toDateTimeString(),
        ]);

        $reponseAudience->assertStatus(201);
        $this->assertEquals(0, Convocation::where('id_audience', $reponseAudience->json('id_audience'))->count());
    }
}
