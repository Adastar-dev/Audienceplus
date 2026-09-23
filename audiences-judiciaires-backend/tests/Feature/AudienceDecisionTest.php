<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AudienceDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function audienceEnCours(): Audience
    {
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);

        return Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now(),
            'mode' => 'PRESENTIEL', 'statut' => 'EN_COURS',
        ]);
    }

    public function test_decider_est_refuse_si_laudience_nest_pas_en_cours(): void
    {
        Mail::fake();
        Http::fake();
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceEnCours();
        $audience->update(['statut' => 'PROGRAMMEE']);

        $reponse = $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/decider", ['type' => 'JUGEMENT']);

        $reponse->assertStatus(409);
    }

    public function test_decider_refuse_un_type_de_decision_invalide(): void
    {
        Mail::fake();
        Http::fake();
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceEnCours();

        $reponse = $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/decider", ['type' => 'NIMPORTEQUOI']);

        $reponse->assertStatus(422);
    }

    public function test_un_jugement_ne_cloture_le_dossier_quapres_fermeture_de_laudience(): void
    {
        Mail::fake();
        Http::fake();
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceEnCours();

        $reponseDecision = $this->actingAs($juge, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/decider",
            ['type' => 'JUGEMENT', 'motif' => 'Divorce prononcé.']
        );
        $reponseDecision->assertOk();

        $audience->refresh();
        $this->assertEquals('JUGEMENT', $audience->type_decision);
        $this->assertEquals('EN_COURS', $audience->statut);
        $this->assertEquals('EN_COURS', $audience->dossier->statut);

        $reponseFermer = $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/fermer");
        $reponseFermer->assertOk();

        $audience->refresh();
        $this->assertEquals('CLOTUREE', $audience->statut);
        $this->assertEquals('JUGE', $audience->dossier->statut);
    }

    public function test_un_renvoi_met_immediatement_a_jour_laudience_et_le_dossier(): void
    {
        Mail::fake();
        Http::fake();
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceEnCours();

        $reponse = $this->actingAs($juge, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/decider",
            ['type' => 'RENVOI', 'motif' => 'Pièces manquantes.']
        );
        $reponse->assertOk();

        $audience->refresh();
        $this->assertEquals('RENVOYEE', $audience->statut);
        $this->assertEquals('RENVOYE', $audience->dossier->statut);
    }

    public function test_une_mise_en_delibere_ne_cloture_pas_le_dossier(): void
    {
        Mail::fake();
        Http::fake();
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceEnCours();

        $reponse = $this->actingAs($juge, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/decider",
            ['type' => 'DELIBERE', 'motif' => 'Délibéré au 30.']
        );
        $reponse->assertOk();

        $audience->refresh();
        $this->assertEquals('DELIBERE', $audience->statut);
        $this->assertEquals('EN_COURS', $audience->dossier->statut);
    }
}
