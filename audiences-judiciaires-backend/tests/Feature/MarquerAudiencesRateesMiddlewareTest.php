<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarquerAudiencesRateesMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_audience_passee_devient_ratee_meme_via_lendpoint_convocations(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subDays(5),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);
        Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE',
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')->getJson('/api/convocations');

        $reponse->assertOk();
        $this->assertEquals('RATEE', $audience->fresh()->statut);
        $this->assertEquals('RATEE', $reponse->json('0.audience.statut'));
    }

    public function test_un_justiciable_ne_peut_plus_confirmer_une_convocation_dont_laudience_est_ratee(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subDays(5),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);
        $convocation = Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE',
        ]);

        $this->actingAs($justiciable, 'sanctum')->getJson('/api/convocations');

        $this->assertEquals('RATEE', $convocation->fresh()->audience->statut);
    }

    public function test_un_juge_en_retard_peut_encore_ouvrir_pendant_le_creneau(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);
        $enRetard = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->subMinutes(20), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);
        $creneauPasse = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->subMinutes(90), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $this->actingAs($juge, 'sanctum')->postJson("/api/audiences/{$enRetard->id_audience}/ouvrir")->assertOk();
        $this->assertEquals('EN_COURS', $enRetard->fresh()->statut);
        $this->assertEquals('RATEE', $creneauPasse->fresh()->statut);
    }
}
