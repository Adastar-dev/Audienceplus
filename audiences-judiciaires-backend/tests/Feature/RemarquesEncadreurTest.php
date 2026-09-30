<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemarquesEncadreurTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(int $idTribunal, string $type = 'DIVORCE'): Dossier
    {
        return Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => $type, 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $idTribunal, 'date_creation' => now(),
        ]);
    }

    public function test_un_greffier_ne_voit_que_les_dossiers_de_son_tribunal(): void
    {
        $dakar = $this->tribunal();
        $thies = $this->tribunal(['nom' => 'Tribunal de Thiès', 'ville' => 'Thiès']);
        $greffier = $this->creerUtilisateur('GREFFIER', ['id_tribunal' => $dakar->id_tribunal]);
        $local = $this->dossier($dakar->id_tribunal);
        $autre = $this->dossier($thies->id_tribunal);

        $this->actingAs($greffier, 'sanctum')->getJson('/api/dossiers')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id_dossier', $local->id_dossier);
        $this->actingAs($greffier, 'sanctum')->getJson("/api/dossiers/{$autre->id_dossier}")->assertStatus(403);
    }

    public function test_un_juge_accede_au_dossier_dont_il_preside_laudience_meme_hors_tribunal(): void
    {
        $dakar = $this->tribunal();
        $thies = $this->tribunal(['nom' => 'Tribunal de Thiès', 'ville' => 'Thiès']);
        $juge = $this->creerUtilisateur('JUGE', ['id_tribunal' => $dakar->id_tribunal]);
        $dossier = $this->dossier($thies->id_tribunal);
        Audience::create([
            'id_dossier' => $dossier->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->addDay(), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $this->actingAs($juge, 'sanctum')->getJson("/api/dossiers/{$dossier->id_dossier}")->assertOk();
    }

    public function test_le_procureur_reste_limite_aux_types_et_a_son_tribunal(): void
    {
        $dakar = $this->tribunal();
        $thies = $this->tribunal(['nom' => 'Tribunal de Thiès', 'ville' => 'Thiès']);
        $procureur = $this->creerUtilisateur('PROCUREUR', ['id_tribunal' => $dakar->id_tribunal]);
        $this->dossier($dakar->id_tribunal, 'ADOPTION');
        $this->dossier($dakar->id_tribunal, 'DIVORCE');
        $this->dossier($thies->id_tribunal, 'ADOPTION');

        $this->actingAs($procureur, 'sanctum')->getJson('/api/dossiers')->assertOk()->assertJsonCount(1);
    }

    public function test_un_compte_non_verifie_ne_peut_pas_demander_un_casier(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);

        $this->actingAs($justiciable, 'sanctum')->postJson('/api/casier-judiciaire')->assertStatus(403);
    }

    public function test_une_requete_non_authentifiee_recoit_401(): void
    {
        $this->postJson('/api/dossiers', [])->assertStatus(401);
    }

    public function test_un_report_accorde_reconvoque_les_autres_parties_et_previent_le_juge(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $demandeur = $this->creerUtilisateur('JUSTICIABLE');
        $autrePartie = $this->creerUtilisateur('JUSTICIABLE');
        $audience = Audience::create([
            'id_dossier' => $this->dossier($this->tribunal()->id_tribunal)->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->addDays(2), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE', 'rappel_48h_le' => now(),
        ]);
        $convocation = Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $demandeur->id_utilisateur, 'canal' => 'EMAIL',
            'statut' => 'AVIS_GREFFIER_FAVORABLE', 'nouvelle_date_proposee' => now()->addDays(20)->setTime(10, 0), 'date_envoi' => now(),
        ]);
        $autre = Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $autrePartie->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'CONFIRMEE', 'date_envoi' => now(),
        ]);

        // Un autre juge ne tranche pas un report qui concerne l'audience d'un collègue.
        $this->actingAs($this->creerUtilisateur('JUGE'), 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/approuver-report")
            ->assertStatus(403);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/approuver-report")
            ->assertOk();

        $this->assertEquals('ENVOYEE', $autre->fresh()->statut);
        $this->assertNull($audience->fresh()->rappel_48h_le);
        $this->assertTrue(Notification::where('id_utilisateur', $autrePartie->id_utilisateur)->where('type', 'Audience reportée')->exists());
        $this->assertTrue(Notification::where('id_utilisateur', $juge->id_utilisateur)->where('type', 'Audience reportée')->exists());
    }
}
