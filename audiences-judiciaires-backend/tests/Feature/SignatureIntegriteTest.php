<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use App\Models\ProcesVerbal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignatureIntegriteTest extends TestCase
{
    use RefreshDatabase;

    private function pvDeTest(): ProcesVerbal
    {
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now(),
            'mode' => 'PRESENTIEL', 'statut' => 'EN_COURS',
        ]);

        return ProcesVerbal::create([
            'id_audience' => $audience->id_audience,
            'contenu' => 'Le tribunal, après en avoir délibéré, prononce le divorce.',
            'statut' => 'EN_VALIDATION',
        ]);
    }

    public function test_lintegrite_est_preservee_juste_apres_la_signature(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $pv = $this->pvDeTest();

        $this->actingAs($juge, 'sanctum')->postJson('/api/signatures', [
            'type_document' => 'PROCES_VERBAL',
            'id_document_signe' => $pv->id_pv,
        ])->assertStatus(201);

        $reponse = $this->actingAs($juge, 'sanctum')->getJson(
            '/api/signatures/verifier?type_document=PROCES_VERBAL&id_document_signe='.$pv->id_pv
        );

        $reponse->assertOk();
        $this->assertTrue($reponse->json('0.integrite_preservee'));
    }

    public function test_une_alteration_du_pv_apres_signature_est_detectee(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $pv = $this->pvDeTest();

        $this->actingAs($juge, 'sanctum')->postJson('/api/signatures', [
            'type_document' => 'PROCES_VERBAL',
            'id_document_signe' => $pv->id_pv,
        ]);

        $pv->update(['contenu' => 'Le tribunal prononce le divorce et attribue tous les biens au demandeur.']);

        $reponse = $this->actingAs($juge, 'sanctum')->getJson(
            '/api/signatures/verifier?type_document=PROCES_VERBAL&id_document_signe='.$pv->id_pv
        );

        $reponse->assertOk();
        $this->assertFalse($reponse->json('0.integrite_preservee'));
    }
}
