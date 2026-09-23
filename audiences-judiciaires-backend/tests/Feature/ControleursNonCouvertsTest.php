<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControleursNonCouvertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_peut_creer_un_tribunal_avec_ses_salles_virtuelles(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $reponse = $this->actingAs($admin, 'sanctum')->postJson('/api/tribunaux', [
            'nom' => 'Tribunal de Grande Instance de Thiès',
            'ville' => 'Thiès',
            'salles_virtuelles' => 3,
        ]);

        $reponse->assertStatus(201);
        $this->assertCount(3, $reponse->json('salles_virtuelles'));
    }

    public function test_un_greffier_ne_peut_pas_creer_un_tribunal(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/tribunaux', [
            'nom' => 'Tribunal Test', 'ville' => 'Dakar', 'salles_virtuelles' => 1,
        ]);

        $reponse->assertStatus(403);
    }

    public function test_la_liste_des_tribunaux_est_accessible_a_tout_utilisateur_authentifie(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $this->tribunal();

        $this->actingAs($justiciable, 'sanctum')->getJson('/api/tribunaux')->assertOk();
    }

    public function test_un_utilisateur_voit_ses_propres_notifications(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        Notification::create([
            'id_utilisateur' => $justiciable->id_utilisateur, 'type' => 'CONVOCATION',
            'message' => 'Vous êtes convoqué.', 'lu' => false, 'date_envoi' => now(),
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')->getJson('/api/notifications');

        $reponse->assertOk();
        $this->assertCount(1, $reponse->json());
    }

    public function test_un_utilisateur_peut_accuser_reception_de_sa_propre_notification(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $notification = Notification::create([
            'id_utilisateur' => $justiciable->id_utilisateur, 'type' => 'CONVOCATION',
            'message' => 'Vous êtes convoqué.', 'lu' => false, 'date_envoi' => now(),
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/notifications/{$notification->id_notification}/accuser-reception");

        $reponse->assertOk();
        $this->assertTrue($notification->fresh()->lu);
    }

    public function test_un_utilisateur_ne_peut_pas_accuser_reception_de_la_notification_dun_autre(): void
    {
        $victime = $this->creerUtilisateur('JUSTICIABLE');
        $attaquant = $this->creerUtilisateur('JUSTICIABLE');
        $notification = Notification::create([
            'id_utilisateur' => $victime->id_utilisateur, 'type' => 'CONVOCATION',
            'message' => 'Vous êtes convoqué.', 'lu' => false, 'date_envoi' => now(),
        ]);

        $reponse = $this->actingAs($attaquant, 'sanctum')
            ->postJson("/api/notifications/{$notification->id_notification}/accuser-reception");

        $reponse->assertStatus(403);
        $this->assertFalse($notification->fresh()->lu);
    }

    public function test_un_greffier_peut_envoyer_un_message_a_un_juge(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $juge = $this->creerUtilisateur('JUGE');

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/messages', [
            'id_destinataire' => $juge->id_utilisateur, 'contenu' => 'Bonjour Monsieur le Juge.',
        ]);

        $reponse->assertStatus(201);
    }

    public function test_un_greffier_ne_peut_pas_envoyer_un_message_a_un_justiciable(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/messages', [
            'id_destinataire' => $justiciable->id_utilisateur, 'contenu' => 'Test.',
        ]);

        $reponse->assertStatus(422);
    }

    public function test_un_justiciable_ne_peut_pas_acceder_a_la_messagerie(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $juge = $this->creerUtilisateur('JUGE');

        $this->actingAs($justiciable, 'sanctum')
            ->postJson('/api/messages', ['id_destinataire' => $juge->id_utilisateur, 'contenu' => 'Test.'])
            ->assertStatus(403);
    }

    public function test_un_justiciable_peut_demander_un_extrait_de_casier_judiciaire(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');

        $reponse = $this->actingAs($justiciable, 'sanctum')->postJson('/api/casier-judiciaire');

        $reponse->assertStatus(201);
        $this->assertEquals('VIERGE', $reponse->json('resultat'));
    }

    public function test_un_avocat_ne_peut_pas_demander_de_casier_judiciaire(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT');

        $this->actingAs($avocat, 'sanctum')->postJson('/api/casier-judiciaire')->assertStatus(403);
    }

    public function test_un_justiciable_peut_demander_a_assister_a_distance(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addDays(3),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')->postJson('/api/demandes-distance', [
            'id_audience' => $audience->id_audience, 'motif' => 'Je réside à l\'étranger.',
        ]);

        $reponse->assertStatus(201);
        $this->assertEquals('EN_ATTENTE', $reponse->json('statut'));
    }

    public function test_on_ne_peut_pas_demander_a_distance_pour_une_audience_deja_en_ligne(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addDays(3),
            'mode' => 'EN_LIGNE', 'statut' => 'PROGRAMMEE',
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')->postJson('/api/demandes-distance', [
            'id_audience' => $audience->id_audience, 'motif' => 'Test.',
        ]);

        $reponse->assertStatus(422);
    }

    public function test_un_administrateur_peut_creer_un_compte_juge(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $reponse = $this->actingAs($admin, 'sanctum')->postJson('/api/utilisateurs', [
            'nom' => 'Nouveau Juge', 'email' => 'nouveau.juge@justice.sn', 'role' => 'JUGE',
        ]);

        $reponse->assertStatus(201);
        $this->assertNotEmpty($reponse->json('mot_de_passe_temporaire'));
        $this->assertDatabaseHas('utilisateurs', ['email' => 'nouveau.juge@justice.sn', 'role' => 'JUGE']);
    }

    public function test_un_administrateur_peut_modifier_un_compte(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $greffier = $this->creerUtilisateur('GREFFIER');

        $reponse = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/utilisateurs/{$greffier->id_utilisateur}", ['nom' => 'Nom Modifié']);

        $reponse->assertOk();
        $this->assertEquals('Nom Modifié', $greffier->fresh()->nom);
    }

    public function test_un_administrateur_peut_supprimer_un_compte(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $greffier = $this->creerUtilisateur('GREFFIER');

        $reponse = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/utilisateurs/{$greffier->id_utilisateur}");

        $reponse->assertStatus(204);
        $this->assertDatabaseMissing('utilisateurs', ['id_utilisateur' => $greffier->id_utilisateur]);
    }

    public function test_un_non_administrateur_ne_peut_pas_gerer_les_comptes(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');

        $this->actingAs($greffier, 'sanctum')->getJson('/api/utilisateurs')->assertStatus(403);
        $this->actingAs($greffier, 'sanctum')
            ->postJson('/api/utilisateurs', ['nom' => 'X', 'email' => 'x@x.sn', 'role' => 'JUGE'])
            ->assertStatus(403);
    }
}
