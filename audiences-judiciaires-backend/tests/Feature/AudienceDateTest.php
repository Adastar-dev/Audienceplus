<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AudienceDateTest extends TestCase
{
    use RefreshDatabase;

    private function dossierDeTest(): Dossier
    {
        $tribunal = $this->tribunal();

        return Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
    }

    public function test_on_ne_peut_pas_programmer_une_audience_a_une_date_passee(): void
    {
        Mail::fake();
        Http::fake();
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $dossier->id_dossier,
            'date_heure' => now()->subDays(2)->toDateTimeString(),
            'mode' => 'PRESENTIEL',
        ]);

        $reponse->assertStatus(422);
    }

    public function test_on_peut_programmer_une_audience_a_une_date_future(): void
    {
        Mail::fake();
        Http::fake();
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $dossier->id_dossier,
            'date_heure' => now()->addDays(5)->toDateTimeString(),
            'mode' => 'PRESENTIEL',
        ]);

        $reponse->assertStatus(201);
    }

    public function test_une_audience_programmee_jamais_ouverte_devient_ratee_a_la_lecture(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subDays(3),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $reponse = $this->actingAs($greffier, 'sanctum')->getJson('/api/audiences');

        $reponse->assertOk();
        $this->assertEquals('RATEE', $audience->fresh()->statut);
    }

    public function test_une_audience_a_venir_ne_devient_pas_ratee(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addDays(3),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $this->actingAs($greffier, 'sanctum')->getJson('/api/audiences');

        $this->assertEquals('PROGRAMMEE', $audience->fresh()->statut);
    }

    public function test_on_ne_peut_pas_ouvrir_une_audience_ratee(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $dossier = $this->dossierDeTest();
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subDays(3),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $reponse = $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/ouvrir");

        $reponse->assertStatus(409);
        $this->assertEquals('RATEE', $audience->fresh()->statut);
    }

    public function test_une_audience_deja_en_cours_ne_devient_pas_ratee_meme_si_la_date_est_passee(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subHours(2),
            'mode' => 'PRESENTIEL', 'statut' => 'EN_COURS',
        ]);

        $this->actingAs($greffier, 'sanctum')->getJson('/api/audiences');

        $this->assertEquals('EN_COURS', $audience->fresh()->statut);
    }
}
