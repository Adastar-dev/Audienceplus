<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use App\Models\Piece;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcureurEtPiecesTest extends TestCase
{
    use RefreshDatabase;

    private function dossierDeTest(): Dossier
    {
        $tribunal = $this->tribunal();

        return Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'ADOPTION', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);
    }

    // --- Avis du procureur ---

    public function test_un_procureur_peut_donner_un_avis_sur_un_dossier(): void
    {
        $procureur = $this->creerUtilisateur('PROCUREUR');
        $dossier = $this->dossierDeTest();

        $reponse = $this->actingAs($procureur, 'sanctum')
            ->patchJson("/api/dossiers/{$dossier->id_dossier}/avis", ['avis' => 'Favorable à la demande.']);

        $reponse->assertOk();
        $this->assertEquals('Favorable à la demande.', $dossier->fresh()->avis_procureur);
        $this->assertEquals($procureur->id_utilisateur, $dossier->fresh()->avis_procureur_par);
        $this->assertNotNull($dossier->fresh()->avis_procureur_date);
    }

    public function test_un_greffier_ne_peut_pas_donner_davis_procureur(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();

        $this->actingAs($greffier, 'sanctum')
            ->patchJson("/api/dossiers/{$dossier->id_dossier}/avis", ['avis' => 'Test'])
            ->assertStatus(403);
    }

    public function test_avis_vide_est_refuse(): void
    {
        $procureur = $this->creerUtilisateur('PROCUREUR');
        $dossier = $this->dossierDeTest();

        $this->actingAs($procureur, 'sanctum')
            ->patchJson("/api/dossiers/{$dossier->id_dossier}/avis", ['avis' => ''])
            ->assertStatus(422);
    }

    // --- Téléchargement de pièces ---

    public function test_un_role_institutionnel_peut_telecharger_une_piece(): void
    {
        Storage::fake('local');
        $procureur = $this->creerUtilisateur('PROCUREUR');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();
        $fichier = UploadedFile::fake()->create('piece.pdf', 100, 'application/pdf');
        $piece = Piece::create([
            'id_dossier' => $dossier->id_dossier, 'depose_par' => $greffier->id_utilisateur,
            'nom' => 'piece.pdf', 'type_fichier' => 'pdf',
            'chemin' => $fichier->store('dossiers/test', 'local'),
            'valide' => false, 'date_depot' => now(),
        ]);

        $this->actingAs($procureur, 'sanctum')
            ->get("/api/pieces/{$piece->id_piece}/telecharger")
            ->assertOk();
    }

    public function test_un_justiciable_non_lie_ne_peut_pas_telecharger_une_piece(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();
        $fichier = UploadedFile::fake()->create('piece.pdf', 100, 'application/pdf');
        $piece = Piece::create([
            'id_dossier' => $dossier->id_dossier, 'depose_par' => $greffier->id_utilisateur,
            'nom' => 'piece.pdf', 'type_fichier' => 'pdf',
            'chemin' => $fichier->store('dossiers/test', 'local'),
            'valide' => false, 'date_depot' => now(),
        ]);

        $this->actingAs($justiciable, 'sanctum')
            ->get("/api/pieces/{$piece->id_piece}/telecharger")
            ->assertStatus(403);
    }

    // --- Demande de comparution à distance ---

    public function test_demande_distance_refusee_sur_une_audience_ratee(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossier = $this->dossierDeTest();
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subDays(2),
            'mode' => 'PRESENTIEL', 'statut' => 'RATEE',
        ]);

        $this->actingAs($justiciable, 'sanctum')->postJson('/api/demandes-distance', [
            'id_audience' => $audience->id_audience, 'motif' => 'Test',
        ])->assertStatus(422);
    }

    public function test_demande_distance_refusee_sur_une_audience_cloturee(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossier = $this->dossierDeTest();
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subDays(2),
            'mode' => 'PRESENTIEL', 'statut' => 'CLOTUREE',
        ]);

        $this->actingAs($justiciable, 'sanctum')->postJson('/api/demandes-distance', [
            'id_audience' => $audience->id_audience, 'motif' => 'Test',
        ])->assertStatus(422);
    }

    public function test_demande_distance_acceptee_sur_une_audience_programmee(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $dossier = $this->dossierDeTest();
        $audience = Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->addDays(3),
            'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $this->actingAs($justiciable, 'sanctum')->postJson('/api/demandes-distance', [
            'id_audience' => $audience->id_audience, 'motif' => 'Test',
        ])->assertStatus(201);
    }
}
