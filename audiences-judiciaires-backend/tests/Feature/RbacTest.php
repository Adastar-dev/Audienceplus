<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_avocat_ne_peut_pas_creer_un_dossier(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT');
        $tribunal = $this->tribunal();

        $reponse = $this->actingAs($avocat, 'sanctum')->postJson('/api/dossiers', [
            'type' => 'DIVORCE',
            'id_tribunal' => $tribunal->id_tribunal,
            'demandeur' => 'X',
            'defendeur' => 'Y',
        ]);

        $reponse->assertStatus(403);
    }

    public function test_un_greffier_peut_creer_un_dossier(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $tribunal = $this->tribunal();

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/dossiers', [
            'type' => 'DIVORCE',
            'id_tribunal' => $tribunal->id_tribunal,
            'demandeur' => 'X',
            'defendeur' => 'Y',
        ]);

        $reponse->assertStatus(201);
    }

    public function test_un_greffier_ne_peut_pas_ouvrir_une_audience(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-0001', 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addDay(),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/ouvrir");

        $reponse->assertStatus(403);
    }

    public function test_les_routes_admin_sont_refusees_a_un_non_administrateur(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');

        $reponse = $this->actingAs($greffier, 'sanctum')->getJson('/api/utilisateurs');

        $reponse->assertStatus(403);
    }

    public function test_un_administrateur_accede_aux_routes_admin(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $reponse = $this->actingAs($admin, 'sanctum')->getJson('/api/utilisateurs');

        $reponse->assertOk();
    }

    public function test_une_route_protegee_refuse_un_utilisateur_non_authentifie(): void
    {
        $reponse = $this->getJson('/api/dossiers');

        $reponse->assertStatus(401);
    }
}
