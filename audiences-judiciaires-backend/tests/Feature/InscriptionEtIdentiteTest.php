<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InscriptionEtIdentiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_peut_sinscrire_sans_email(): void
    {
        $reponse = $this->postJson('/api/inscription', [
            'nom' => 'Sans Email',
            'telephone' => '+221799999911',
            'cni' => '1111111111111',
            'mot_de_passe' => 'password123',
            'role' => 'JUSTICIABLE',
        ]);

        $reponse->assertStatus(201);
    }

    public function test_on_ne_peut_pas_sinscrire_avec_un_telephone_deja_utilise(): void
    {
        $this->postJson('/api/inscription', [
            'nom' => 'Premier', 'telephone' => '+221799999912', 'cni' => '2222222222222',
            'mot_de_passe' => 'password123', 'role' => 'JUSTICIABLE',
        ])->assertStatus(201);

        $reponse = $this->postJson('/api/inscription', [
            'nom' => 'Doublon', 'telephone' => '+221799999912', 'cni' => '3333333333333',
            'mot_de_passe' => 'password123', 'role' => 'JUSTICIABLE',
        ]);

        $reponse->assertStatus(422);
    }

    public function test_un_numero_de_cni_mal_forme_est_rejete(): void
    {
        $reponse = $this->postJson('/api/inscription', [
            'nom' => 'Test', 'telephone' => '+221799999913', 'cni' => 'abc',
            'mot_de_passe' => 'password123', 'role' => 'JUSTICIABLE',
        ]);

        $reponse->assertStatus(422);
    }

    public function test_un_administrateur_qui_cree_un_avocat_doit_fournir_le_numero_de_barreau(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $reponse = $this->actingAs($admin, 'sanctum')->postJson('/api/utilisateurs', [
            'nom' => 'Avocat Sans Barreau', 'email' => 'sansbarreau@test.sn', 'role' => 'AVOCAT',
        ]);

        $reponse->assertStatus(422);
    }

    public function test_un_administrateur_qui_cree_un_avocat_avec_numero_de_barreau_reussit(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $reponse = $this->actingAs($admin, 'sanctum')->postJson('/api/utilisateurs', [
            'nom' => 'Avocat OK', 'email' => 'avocatok@test.sn', 'role' => 'AVOCAT',
            'numero_barreau' => 'BSN-2026-001',
        ]);

        $reponse->assertStatus(201);
        $this->assertEquals('BSN-2026-001', $reponse->json('utilisateur.numero_barreau'));
    }

    public function test_un_administrateur_qui_cree_un_justiciable_doit_fournir_la_cni(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $reponse = $this->actingAs($admin, 'sanctum')->postJson('/api/utilisateurs', [
            'nom' => 'Justiciable Sans CNI', 'email' => 'sanscni@test.sn', 'role' => 'JUSTICIABLE',
        ]);

        $reponse->assertStatus(422);
    }

    // --- Vérification OCR du numéro de CNI ---

    private function audienceDeTest(): Audience
    {
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);

        return Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now(),
            'mode' => 'EN_LIGNE', 'statut' => 'EN_COURS',
        ]);
    }

    public function test_numero_ocr_concordant_avec_la_cni_declaree(): void
    {
        Storage::fake('local');
        config(['services.openai.api_key' => 'test']);
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['cni' => '9990001112223']);
        Http::fake([
            'api.openai.com/*' => Http::response(
                ['choices' => [['message' => ['content' => '9990001112223']]]], 200
            ),
        ]);
        $audience = $this->audienceDeTest();

        $this->actingAs($justiciable, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/verification-identite/cni",
            ['cni' => UploadedFile::fake()->image('cni.jpg', 400, 250)]
        );

        $reponse = $this->actingAs($justiciable, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/verification-identite/selfie",
            ['selfie' => UploadedFile::fake()->image('selfie.jpg', 400, 400)]
        );

        $reponse->assertOk();
        $this->assertTrue($reponse->json('numero_cni_concorde'));
    }

    public function test_numero_ocr_non_concordant_avec_la_cni_declaree(): void
    {
        Storage::fake('local');
        config(['services.openai.api_key' => 'test']);
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['cni' => '9990001112223']);
        Http::fake([
            'api.openai.com/*' => Http::response(
                ['choices' => [['message' => ['content' => '0000000000000']]]], 200
            ),
        ]);
        $audience = $this->audienceDeTest();

        $this->actingAs($justiciable, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/verification-identite/cni",
            ['cni' => UploadedFile::fake()->image('cni.jpg', 400, 250)]
        );

        $reponse = $this->actingAs($justiciable, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/verification-identite/selfie",
            ['selfie' => UploadedFile::fake()->image('selfie.jpg', 400, 400)]
        );

        $reponse->assertOk();
        $this->assertFalse($reponse->json('numero_cni_concorde'));
    }

    public function test_ocr_sans_cle_api_ne_plante_pas(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();

        $this->actingAs($justiciable, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/verification-identite/cni",
            ['cni' => UploadedFile::fake()->image('cni.jpg', 400, 250)]
        );

        $reponse = $this->actingAs($justiciable, 'sanctum')->postJson(
            "/api/audiences/{$audience->id_audience}/verification-identite/selfie",
            ['selfie' => UploadedFile::fake()->image('selfie.jpg', 400, 400)]
        );

        $reponse->assertOk();
        $this->assertNull($reponse->json('numero_cni_detecte'));
        $this->assertNull($reponse->json('numero_cni_concorde'));
    }
}
