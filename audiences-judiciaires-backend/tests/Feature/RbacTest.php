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

        // Un autre juge que celui de l'audience ne la conduit pas non plus, et une
        // personne étrangère au dossier n'en voit pas les participants.
        $juge = $this->creerUtilisateur('JUGE');
        $autreJuge = $this->creerUtilisateur('JUGE');
        $audience->update(['id_juge' => $juge->id_utilisateur]);
        foreach (['ouvrir', 'fermer', 'decider'] as $action) {
            $this->actingAs($autreJuge, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/{$action}", ['type' => 'RENVOI'])->assertStatus(403);
        }
        $this->actingAs($this->creerUtilisateur('JUSTICIABLE'), 'sanctum')->getJson("/api/audiences/{$audience->id_audience}/participants")->assertStatus(403);
        // Pas d'ouverture la veille : seulement 30 minutes avant l'heure prévue.
        $this->actingAs($juge, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/ouvrir")->assertStatus(409);
        $audience->update(['date_heure' => now()->addMinutes(20)]);
        $this->actingAs($juge, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/ouvrir")->assertOk();
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
