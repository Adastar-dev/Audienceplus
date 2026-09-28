<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatistiquesTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(string $type, string $statut, ?int $joursAvantJugement = null): Dossier
    {
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => $type, 'statut' => $statut,
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal,
            'date_creation' => now()->subDays(100),
        ]);

        if ($joursAvantJugement !== null) {
            Audience::create([
                'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subDays(100 - $joursAvantJugement),
                'mode' => 'PRESENTIEL', 'statut' => 'CLOTUREE', 'type_decision' => 'JUGEMENT',
            ]);
        }

        return $dossier;
    }

    public function test_statistiques_par_type_de_dossier(): void
    {
        $this->dossier('DIVORCE', 'JUGE', 30);
        $this->dossier('DIVORCE', 'ARCHIVE', 50);
        $this->dossier('DIVORCE', 'EN_COURS');
        $this->dossier('ADOPTION', 'EN_COURS');
        Audience::create([
            'id_dossier' => Dossier::first()->id_dossier, 'date_heure' => now()->subDays(80),
            'mode' => 'PRESENTIEL', 'statut' => 'RENVOYEE', 'type_decision' => 'RENVOI',
        ]);

        $reponse = $this->actingAs($this->creerUtilisateur('ADMINISTRATEUR'), 'sanctum')
            ->getJson('/api/statistiques')
            ->assertOk()
            ->assertJsonPath('totaux.dossiers', 4)
            ->assertJsonPath('totaux.jugements', 2)
            ->assertJsonPath('totaux.archives', 1)
            ->assertJsonPath('par_type.0.type', 'DIVORCE')
            ->assertJsonPath('par_type.0.total', 3)
            ->assertJsonPath('par_type.0.en_cours', 1)
            ->assertJsonPath('par_type.0.juges', 1)
            ->assertJsonPath('par_type.0.archives', 1);

        $this->assertEquals(40, $reponse->json('par_type.0.delai_moyen_jours'));
        $this->assertNull($reponse->json('par_type.1.delai_moyen_jours'));
        $this->assertEquals(33.3, $reponse->json('taux_renvoi'));
    }

    public function test_seul_ladministrateur_voit_les_statistiques(): void
    {
        $this->actingAs($this->creerUtilisateur('GREFFIER'), 'sanctum')
            ->getJson('/api/statistiques')
            ->assertStatus(403);
    }
}
