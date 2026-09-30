<?php

namespace Tests\Feature;

use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PieceUploadTest extends TestCase
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

    public function test_un_vrai_pdf_est_accepte(): void
    {
        Storage::fake('local');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();

        $fichier = UploadedFile::fake()->create('piece.pdf', 10, 'application/pdf');

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/dossiers/{$dossier->id_dossier}/pieces", ['fichier' => $fichier]);

        $reponse->assertStatus(201);
    }

    public function test_un_fichier_executable_deguise_en_pdf_est_rejete(): void
    {
        Storage::fake('local');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();

        $cheminReel = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($cheminReel, '<?php system($_GET["c"]); ?>');
        $fichier = new UploadedFile($cheminReel, 'malveillant.pdf', 'application/pdf', null, true);

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/dossiers/{$dossier->id_dossier}/pieces", ['fichier' => $fichier]);

        $reponse->assertStatus(422);

        @unlink($cheminReel);
    }

    public function test_un_type_de_fichier_non_pertinent_est_rejete(): void
    {
        Storage::fake('local');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();

        $fichier = UploadedFile::fake()->create('archive.zip', 10, 'application/zip');

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/dossiers/{$dossier->id_dossier}/pieces", ['fichier' => $fichier]);

        $reponse->assertStatus(422);
    }

    public function test_la_suppression_efface_le_fichier_et_reste_limitee_au_tribunal(): void
    {
        Storage::fake('local');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->dossierDeTest();
        $greffier->update(['id_tribunal' => $dossier->id_tribunal]);
        $autreTribunal = $this->tribunal(['nom' => 'Tribunal de Thiès', 'ville' => 'Thiès']);
        $greffierAilleurs = $this->creerUtilisateur('GREFFIER', ['id_tribunal' => $autreTribunal->id_tribunal]);

        $piece = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/dossiers/{$dossier->id_dossier}/pieces", ['fichier' => UploadedFile::fake()->create('piece.pdf', 10, 'application/pdf')])
            ->assertStatus(201)->json();
        Storage::disk('local')->assertExists($piece['chemin']);

        $this->actingAs($greffierAilleurs, 'sanctum')->deleteJson("/api/pieces/{$piece['id_piece']}")->assertStatus(403);
        $this->actingAs($greffierAilleurs, 'sanctum')->postJson("/api/pieces/{$piece['id_piece']}/valider")->assertStatus(403);
        $this->actingAs($greffierAilleurs, 'sanctum')->patchJson("/api/dossiers/{$dossier->id_dossier}", ['statut' => 'RENVOYE'])->assertStatus(403);
        $this->actingAs($greffier, 'sanctum')->patchJson("/api/dossiers/{$dossier->id_dossier}", ['statut' => 'RENVOYE'])->assertOk();

        $this->actingAs($greffier, 'sanctum')->deleteJson("/api/pieces/{$piece['id_piece']}")->assertStatus(204);
        Storage::disk('local')->assertMissing($piece['chemin']);
    }
}
