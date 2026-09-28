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

    // Inscription avec la photo de CNI désormais obligatoire (envoi multipart).
    private function inscrire(array $donnees)
    {
        Storage::fake('local');

        return $this->post(
            '/api/inscription',
            $donnees + [
                'cni_photo' => UploadedFile::fake()->image('cni.jpg', 600, 400),
                'cni_verso' => UploadedFile::fake()->image('cni-verso.jpg', 600, 400),
            ],
            ['Accept' => 'application/json'],
        );
    }

    // L'email est obligatoire : convocations et code de vérification partent par email.
    public function test_on_ne_peut_pas_sinscrire_sans_email(): void
    {
        $reponse = $this->inscrire([
            'nom' => 'Sans Email',
            'telephone' => '+221799999911',
            'cni' => '1111111111111',
            'mot_de_passe' => 'password123',
            'role' => 'JUSTICIABLE',
        ]);

        $reponse->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_on_ne_peut_pas_sinscrire_avec_un_telephone_deja_utilise(): void
    {
        $this->inscrire([
            'nom' => 'Premier', 'email' => 'premier@mail.sn', 'telephone' => '+221799999912', 'cni' => '2222222222222',
            'mot_de_passe' => 'password123', 'role' => 'JUSTICIABLE',
        ])->assertStatus(201);

        $reponse = $this->inscrire([
            'nom' => 'Doublon', 'email' => 'doublon@mail.sn', 'telephone' => '+221799999912', 'cni' => '3333333333333',
            'mot_de_passe' => 'password123', 'role' => 'JUSTICIABLE',
        ]);

        $reponse->assertStatus(422);
    }

    public function test_un_numero_de_cni_mal_forme_est_rejete(): void
    {
        $reponse = $this->inscrire([
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

    private function deposerCni(\App\Models\Utilisateur $utilisateur)
    {
        return $this->actingAs($utilisateur, 'sanctum')->post(
            '/api/mon-identite/cni',
            [
                'cni_photo' => UploadedFile::fake()->image('cni.jpg', 400, 250),
                'cni_verso' => UploadedFile::fake()->image('cni-verso.jpg', 400, 250),
            ],
            ['Accept' => 'application/json'],
        );
    }

    public function test_le_numero_est_relu_sur_le_verso_si_le_recto_ne_donne_rien(): void
    {
        Storage::fake('local');
        config(['services.ia.api_key' => 'test']);
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['cni' => '9990001112223', 'identite_verifiee' => false]);
        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'AUCUN']]]], 200)
            ->push(['choices' => [['message' => ['content' => '9990001112223']]]], 200)]);

        $this->deposerCni($justiciable)->assertOk();

        $this->assertTrue($justiciable->fresh()->numero_cni_concorde);
        $this->assertNotNull($justiciable->fresh()->cni_verso_path);
    }

    public function test_numero_ocr_concordant_avec_la_cni_declaree(): void
    {
        Storage::fake('local');
        config(['services.ia.api_key' => 'test']);
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['cni' => '9990001112223', 'identite_verifiee' => false]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '9990001112223']]]], 200)]);

        $reponse = $this->deposerCni($justiciable);

        $reponse->assertOk();
        $this->assertTrue($reponse->json('numero_cni_concorde'));
        $this->assertTrue($reponse->json('a_photo_cni'));
    }

    public function test_numero_ocr_non_concordant_avec_la_cni_declaree(): void
    {
        Storage::fake('local');
        config(['services.ia.api_key' => 'test']);
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['cni' => '9990001112223', 'identite_verifiee' => false]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '0000000000000']]]], 200)]);

        $reponse = $this->deposerCni($justiciable);

        $reponse->assertOk();
        $this->assertFalse($reponse->json('numero_cni_concorde'));
    }

    public function test_ocr_sans_cle_api_ne_plante_pas(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);

        $reponse = $this->deposerCni($justiciable);

        $reponse->assertOk();
        $this->assertNull($reponse->json('numero_cni_detecte'));
        $this->assertNull($reponse->json('numero_cni_concorde'));
    }

    public function test_linscription_sans_photo_de_cni_est_refusee(): void
    {
        $this->postJson('/api/inscription', [
            'nom' => 'Sans Photo', 'telephone' => '+221799999988', 'cni' => '1234512345123',
            'mot_de_passe' => 'password123', 'role' => 'JUSTICIABLE',
        ])->assertStatus(422)->assertJsonValidationErrors('cni_photo');
    }
}
