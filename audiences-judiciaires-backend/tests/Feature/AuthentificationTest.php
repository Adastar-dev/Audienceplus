<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_connexion_reussie_avec_les_bons_identifiants(): void
    {
        $utilisateur = $this->creerUtilisateur('GREFFIER', ['email' => 'greffier@justice.sn']);

        $reponse = $this->postJson('/api/login', [
            'identifiant' => 'greffier@justice.sn',
            'mot_de_passe' => 'password',
        ]);

        $reponse->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_connexion_echoue_avec_un_mauvais_mot_de_passe(): void
    {
        $this->creerUtilisateur('GREFFIER', ['email' => 'greffier@justice.sn']);

        $reponse = $this->postJson('/api/login', [
            'identifiant' => 'greffier@justice.sn',
            'mot_de_passe' => 'mauvais_mot_de_passe',
        ]);

        $reponse->assertStatus(401);
    }

    public function test_le_compte_est_bloque_apres_cinq_echecs_consecutifs(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->creerUtilisateur('GREFFIER', ['email' => 'greffier@justice.sn']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'identifiant' => 'greffier@justice.sn',
                'mot_de_passe' => 'mauvais_mot_de_passe',
            ]);
        }

        $reponse = $this->postJson('/api/login', [
            'identifiant' => 'greffier@justice.sn',
            'mot_de_passe' => 'password',
        ]);

        $reponse->assertStatus(423);
    }

    public function test_un_seul_echec_ne_bloque_pas_le_compte(): void
    {
        $this->creerUtilisateur('GREFFIER', ['email' => 'greffier@justice.sn']);

        $this->postJson('/api/login', [
            'identifiant' => 'greffier@justice.sn',
            'mot_de_passe' => 'mauvais_mot_de_passe',
        ]);

        $reponse = $this->postJson('/api/login', [
            'identifiant' => 'greffier@justice.sn',
            'mot_de_passe' => 'password',
        ]);

        $reponse->assertOk();
    }

    public function test_la_deconnexion_revoque_le_token(): void
    {
        $utilisateur = $this->creerUtilisateur('GREFFIER');
        $token = $utilisateur->createToken('auth_token')->plainTextToken;

        $reponseLogout = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');
        $reponseLogout->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $utilisateur->id_utilisateur,
        ]);
    }
}
