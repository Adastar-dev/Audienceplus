<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use App\Models\PartieDossier;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisionEtCasierQrTest extends TestCase
{
    use RefreshDatabase;

    private function audienceJugee(Utilisateur $partie, string $type = 'DIVORCE', array $overrides = []): Audience
    {
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => $type, 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);
        PartieDossier::create([
            'id_dossier' => $dossier->id_dossier, 'id_utilisateur' => $partie->id_utilisateur, 'role_partie' => 'DEMANDEUR',
        ]);

        return Audience::create(array_merge([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now()->subHour(),
            'mode' => 'EN_LIGNE', 'statut' => 'CLOTUREE',
            'type_decision' => 'JUGEMENT', 'motif_decision' => 'Le divorce est prononcé.',
        ], $overrides));
    }

    public function test_chaque_casier_recoit_une_reference_unique_et_un_qr_code(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');

        $premier = $this->actingAs($justiciable, 'sanctum')->postJson('/api/casier-judiciaire')->assertStatus(201);
        $second = $this->actingAs($justiciable, 'sanctum')->postJson('/api/casier-judiciaire')->assertStatus(201);

        $this->assertMatchesRegularExpression('/^AJ-CJ-\d{4}-\d{6}-[A-Z0-9]{4}$/', $premier->json('qr_code'));
        $this->assertNotEquals($premier->json('qr_code'), $second->json('qr_code'));
        $this->assertStringStartsWith('data:image/png;base64,', $premier->json('qr_image'));
    }

    public function test_une_partie_telecharge_la_decision_en_pdf(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceJugee($justiciable);

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->get("/api/audiences/{$audience->id_audience}/decision/pdf")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_une_personne_etrangere_au_dossier_ne_telecharge_pas_la_decision(): void
    {
        $audience = $this->audienceJugee($this->creerUtilisateur('JUSTICIABLE'));
        $autre = $this->creerUtilisateur('JUSTICIABLE');

        $this->actingAs($autre, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/decision/pdf")
            ->assertStatus(403);
    }

    public function test_pas_de_pdf_tant_que_la_decision_nest_pas_rendue(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceJugee($justiciable, 'DIVORCE', ['statut' => 'EN_COURS', 'type_decision' => null]);

        $this->actingAs($justiciable, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/decision/pdf")
            ->assertStatus(404);
    }

    public function test_une_partie_voit_les_decisions_de_ses_dossiers(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceJugee($justiciable);
        $this->audienceJugee($this->creerUtilisateur('JUSTICIABLE'));

        $this->actingAs($justiciable, 'sanctum')
            ->getJson('/api/audiences')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id_audience', $audience->id_audience)
            ->assertJsonPath('0.type_decision', 'JUGEMENT');
    }

    public function test_un_renvoi_est_aussi_telechargeable(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceJugee($justiciable, 'DIVORCE', ['statut' => 'RENVOYEE', 'type_decision' => 'RENVOI']);

        $this->actingAs($justiciable, 'sanctum')
            ->get("/api/audiences/{$audience->id_audience}/decision/pdf")
            ->assertOk();
    }

    public function test_le_titulaire_telecharge_son_extrait_de_casier_et_pas_celui_dun_autre(): void
    {
        $titulaire = $this->creerUtilisateur('JUSTICIABLE');
        $autre = $this->creerUtilisateur('JUSTICIABLE');
        $id = $this->actingAs($titulaire, 'sanctum')->postJson('/api/casier-judiciaire')->json('id_casier');

        $reponse = $this->actingAs($titulaire, 'sanctum')->get("/api/casier-judiciaire/{$id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $reponse->getContent());

        $this->actingAs($autre, 'sanctum')->getJson("/api/casier-judiciaire/{$id}/pdf")->assertStatus(403);
    }

    // --- Vérification publique à partir du QR code ---

    public function test_la_decision_se_verifie_sans_connexion_et_detecte_une_empreinte_falsifiee(): void
    {
        $audience = $this->audienceJugee($this->creerUtilisateur('JUSTICIABLE'));
        $reference = \App\Http\Controllers\DecisionController::reference($audience);
        $empreinte = \App\Http\Controllers\DecisionController::empreinte($audience);

        $this->getJson("/api/verification/{$reference}?e={$empreinte}")
            ->assertOk()
            ->assertJsonPath('authentique', true)
            ->assertJsonPath('empreinte_conforme', true)
            ->assertJsonPath('dossier', $audience->dossier->numero)
            ->assertJsonMissingPath('parties');

        // Décision modifiée après impression : l'empreinte imprimée ne correspond plus.
        $audience->update(['motif_decision' => 'Motif modifié.']);
        $this->getJson("/api/verification/{$reference}?e={$empreinte}")
            ->assertOk()
            ->assertJsonPath('empreinte_conforme', false);
    }

    public function test_lextrait_de_casier_se_verifie_avec_un_nom_masque(): void
    {
        $titulaire = $this->creerUtilisateur('JUSTICIABLE', ['nom' => 'Awa Diop']);
        $casier = $this->actingAs($titulaire, 'sanctum')->postJson('/api/casier-judiciaire')->json();
        $this->assertStringEndsWith('/verification/'.$casier['qr_code'], $casier['url_verification']);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/verification/'.$casier['qr_code'])
            ->assertOk()
            ->assertJsonPath('type', 'CASIER')
            ->assertJsonPath('titulaire', 'A. D***')
            ->assertJsonPath('resultat', 'Néant');
    }

    public function test_une_reference_inconnue_ou_une_decision_non_rendue_nest_pas_authentifiee(): void
    {
        $this->getJson('/api/verification/AJ-CJ-2026-999999-ZZZZ')->assertStatus(404)->assertJsonPath('authentique', false);
        $this->getJson('/api/verification/nimporte-quoi')->assertStatus(404);

        $audience = $this->audienceJugee($this->creerUtilisateur('JUSTICIABLE'), 'DIVORCE', ['statut' => 'EN_COURS', 'type_decision' => null]);
        $this->getJson('/api/verification/'.\App\Http\Controllers\DecisionController::reference($audience))->assertStatus(404);
    }

    public function test_le_pdf_complet_parties_comparution_avis_et_pv_scelle(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $avocat = $this->creerUtilisateur('AVOCAT');
        $procureur = $this->creerUtilisateur('PROCUREUR');
        $audience = $this->audienceJugee($justiciable, 'ADOPTION', ['mode' => 'PRESENTIEL']);
        $audience->dossier->update(['avis_procureur' => 'Avis favorable.', 'avis_procureur_par' => $procureur->id_utilisateur]);
        $this->lierPartie($audience->dossier, $avocat);
        \App\Models\DemandeDistance::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'motif' => 'Réside à Paris', 'statut' => 'APPROUVEE', 'date_demande' => now(),
        ]);
        \App\Models\ParticipationAudience::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'role_audience' => 'JUSTICIABLE', 'present' => true,
        ]);
        $pv = \App\Models\ProcesVerbal::create([
            'id_audience' => $audience->id_audience, 'contenu' => 'Déroulement de l\'audience.',
            'statut' => 'CLOTURE', 'date_validation' => now(),
        ]);
        \App\Models\Signature::create([
            'id_utilisateur' => $this->creerUtilisateur('JUGE')->id_utilisateur, 'type_document' => 'PROCES_VERBAL',
            'id_document_signe' => $pv->id_pv, 'hash' => str_repeat('a', 64), 'date_signature' => now(),
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->get("/api/audiences/{$audience->id_audience}/decision/pdf")
            ->assertOk();
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }
}
