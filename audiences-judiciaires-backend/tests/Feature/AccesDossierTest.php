<?php

namespace Tests\Feature;

use App\Models\Dossier;
use App\Models\PartieDossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesDossierTest extends TestCase
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

    public function test_un_justiciable_voit_uniquement_ses_propres_dossiers_dans_la_liste(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossierLie = $this->dossierDeTest();
        $dossierAutrui = $this->dossierDeTest();
        PartieDossier::create([
            'id_dossier' => $dossierLie->id_dossier, 'id_utilisateur' => $justiciable->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')->getJson('/api/dossiers');

        $reponse->assertOk();
        $ids = collect($reponse->json())->pluck('id_dossier');
        $this->assertTrue($ids->contains($dossierLie->id_dossier));
        $this->assertFalse($ids->contains($dossierAutrui->id_dossier));
    }

    public function test_un_justiciable_ne_peut_pas_consulter_le_dossier_dun_autre(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossier = $this->dossierDeTest();

        $this->actingAs($justiciable, 'sanctum')
            ->getJson("/api/dossiers/{$dossier->id_dossier}")
            ->assertStatus(403);
    }

    public function test_un_avocat_non_verifie_ne_voit_aucun_dossier_meme_sil_y_est_lie(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT', ['identite_verifiee' => false]);
        $dossier = $this->dossierDeTest();
        PartieDossier::create([
            'id_dossier' => $dossier->id_dossier, 'id_utilisateur' => $avocat->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);

        $this->actingAs($avocat, 'sanctum')->getJson('/api/dossiers')->assertOk()->assertJsonCount(0);

        $this->actingAs($avocat, 'sanctum')
            ->getJson("/api/dossiers/{$dossier->id_dossier}")
            ->assertStatus(403);
    }

    public function test_un_justiciable_non_verifie_ne_voit_pas_son_propre_dossier(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);
        $dossier = $this->dossierDeTest();
        PartieDossier::create([
            'id_dossier' => $dossier->id_dossier, 'id_utilisateur' => $justiciable->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);

        $this->actingAs($justiciable, 'sanctum')->getJson('/api/dossiers')->assertOk()->assertJsonCount(0);

        $this->actingAs($justiciable, 'sanctum')
            ->getJson("/api/dossiers/{$dossier->id_dossier}")
            ->assertStatus(403);
    }

    public function test_un_avocat_verifie_et_lie_peut_consulter_le_dossier(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT', ['identite_verifiee' => true]);
        $dossier = $this->dossierDeTest();
        PartieDossier::create([
            'id_dossier' => $dossier->id_dossier, 'id_utilisateur' => $avocat->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);

        $this->actingAs($avocat, 'sanctum')
            ->getJson("/api/dossiers/{$dossier->id_dossier}")
            ->assertOk();
    }

    public function test_le_greffier_et_le_juge_voient_tous_les_dossiers(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $juge = $this->creerUtilisateur('JUGE');
        $dossier = $this->dossierDeTest();

        $this->actingAs($greffier, 'sanctum')->getJson("/api/dossiers/{$dossier->id_dossier}")->assertOk();
        $this->actingAs($juge, 'sanctum')->getJson("/api/dossiers/{$dossier->id_dossier}")->assertOk();
    }

    public function test_le_procureur_ne_voit_que_les_dossiers_communiques_au_parquet(): void
    {
        $procureur = $this->creerUtilisateur('PROCUREUR');
        $adoption = $this->dossierDeTest();
        $adoption->update(['type' => 'ADOPTION']);
        $divorce = $this->dossierDeTest();
        $divorce->update(['type' => 'DIVORCE']);

        $this->actingAs($procureur, 'sanctum')->getJson("/api/dossiers/{$adoption->id_dossier}")->assertOk();
        $this->actingAs($procureur, 'sanctum')->getJson("/api/dossiers/{$divorce->id_dossier}")->assertStatus(403);

        $ids = collect($this->actingAs($procureur, 'sanctum')->getJson('/api/dossiers')->json())->pluck('id_dossier');
        $this->assertTrue($ids->contains($adoption->id_dossier));
        $this->assertFalse($ids->contains($divorce->id_dossier));

        $this->actingAs($procureur, 'sanctum')
            ->patchJson("/api/dossiers/{$divorce->id_dossier}/avis", ['avis' => 'Avis.'])
            ->assertStatus(403);
    }

    public function test_un_justiciable_ne_voit_pas_les_audiences_des_autres_dossiers(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossier = $this->dossierDeTest();
        $audience = \App\Models\Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addDay(), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $this->actingAs($justiciable, 'sanctum')->getJson("/api/audiences/{$audience->id_audience}")->assertStatus(403);
        $this->assertEmpty($this->actingAs($justiciable, 'sanctum')->getJson('/api/audiences')->json());
    }

    public function test_un_avocat_non_verifie_ne_peut_pas_consulter_les_pieces_du_dossier(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT', ['identite_verifiee' => false]);
        $dossier = $this->dossierDeTest();
        PartieDossier::create([
            'id_dossier' => $dossier->id_dossier, 'id_utilisateur' => $avocat->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);

        $this->actingAs($avocat, 'sanctum')
            ->getJson("/api/dossiers/{$dossier->id_dossier}/pieces")
            ->assertStatus(403);
    }

    public function test_un_administrateur_peut_lister_et_verifier_un_avocat(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $avocat = $this->creerUtilisateur('AVOCAT', ['identite_verifiee' => false, 'cni_photo_path' => 'cni-comptes/test.jpg', 'cni_verso_path' => 'cni-comptes/test-verso.jpg']);

        $reponseListe = $this->actingAs($admin, 'sanctum')->getJson('/api/comptes-a-verifier');
        $reponseListe->assertOk();
        $this->assertTrue(collect($reponseListe->json())->pluck('id_utilisateur')->contains($avocat->id_utilisateur));

        $reponseVerif = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/utilisateurs/{$avocat->id_utilisateur}/verifier-identite");
        $reponseVerif->assertOk();
        $this->assertTrue($avocat->fresh()->identite_verifiee);
    }

    public function test_un_administrateur_peut_lister_et_verifier_un_justiciable(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false, 'cni_photo_path' => 'cni-comptes/test.jpg', 'cni_verso_path' => 'cni-comptes/test-verso.jpg']);

        $reponseListe = $this->actingAs($admin, 'sanctum')->getJson('/api/comptes-a-verifier');
        $reponseListe->assertOk();
        $this->assertTrue(collect($reponseListe->json())->pluck('id_utilisateur')->contains($justiciable->id_utilisateur));

        $reponseVerif = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/utilisateurs/{$justiciable->id_utilisateur}/verifier-identite");
        $reponseVerif->assertOk();
        $this->assertTrue($justiciable->fresh()->identite_verifiee);
    }

    public function test_un_greffier_ne_peut_pas_verifier_un_avocat(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $avocat = $this->creerUtilisateur('AVOCAT', ['identite_verifiee' => false]);

        $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/utilisateurs/{$avocat->id_utilisateur}/verifier-identite")
            ->assertStatus(403);
    }

    public function test_verifier_identite_refuse_un_compte_qui_nest_ni_avocat_ni_justiciable(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $juge = $this->creerUtilisateur('JUGE');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/utilisateurs/{$juge->id_utilisateur}/verifier-identite")
            ->assertStatus(422);
    }

    public function test_un_compte_sans_photo_de_cni_ne_peut_pas_etre_verifie(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/utilisateurs/{$justiciable->id_utilisateur}/verifier-identite")
            ->assertStatus(422);

        $this->assertFalse($justiciable->fresh()->identite_verifiee);
    }
}
