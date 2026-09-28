<?php

namespace Tests\Feature;

use App\Models\LogActivite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalActiviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_action_reussie_est_journalisee_avec_son_auteur_et_sa_cible(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $compte = $this->creerUtilisateur('JUSTICIABLE', ['nom' => 'Awa Diop']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/utilisateurs/{$compte->id_utilisateur}", ['nom' => 'Awa Diop Ndiaye'])
            ->assertOk();

        $log = LogActivite::sole();
        $this->assertEquals($admin->id_utilisateur, $log->id_utilisateur);
        $this->assertStringStartsWith("Modification d'un compte - compte de Awa Diop", $log->action);
        $this->assertNotNull($log->adresse_ip);
    }

    public function test_une_creation_reprend_lelement_cree(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');

        $this->actingAs($justiciable, 'sanctum')->postJson('/api/casier-judiciaire')->assertStatus(201);

        $this->assertStringContainsString('référence AJ-CJ-', LogActivite::sole()->action);
    }

    public function test_les_lectures_et_les_actions_refusees_ne_sont_pas_journalisees(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT');

        $this->actingAs($avocat, 'sanctum')->getJson('/api/dossiers')->assertOk();
        $this->actingAs($avocat, 'sanctum')->postJson('/api/casier-judiciaire')->assertStatus(403);

        $this->assertEquals(0, LogActivite::count());
    }

    public function test_un_echec_de_connexion_est_journalise(): void
    {
        $utilisateur = $this->creerUtilisateur('JUSTICIABLE');

        $this->postJson('/api/login', ['identifiant' => $utilisateur->email, 'mot_de_passe' => 'mauvais'])
            ->assertStatus(401);

        $this->assertEquals('Échec de connexion', LogActivite::where('id_utilisateur', $utilisateur->id_utilisateur)->sole()->action);
    }
}
