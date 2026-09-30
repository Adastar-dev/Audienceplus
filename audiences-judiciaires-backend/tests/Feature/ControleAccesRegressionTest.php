<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ControleAccesRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function audienceDeTest(): Audience
    {
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);

        return Audience::create([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now(),
            'mode' => 'PRESENTIEL', 'statut' => 'EN_COURS',
        ]);
    }

    public function test_un_justiciable_ne_peut_pas_confirmer_la_convocation_dun_autre(): void
    {
        Mail::fake();
        Http::fake();

        $victime = $this->creerUtilisateur('JUSTICIABLE');
        $attaquant = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();
        $convocation = Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $victime->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE',
        ]);

        $reponse = $this->actingAs($attaquant, 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/confirmer");

        $reponse->assertStatus(403);
        $this->assertEquals('ENVOYEE', $convocation->fresh()->statut);
    }

    public function test_un_justiciable_ne_peut_pas_demander_de_report_pour_un_autre(): void
    {
        $victime = $this->creerUtilisateur('JUSTICIABLE');
        $attaquant = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();
        $convocation = Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $victime->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE',
        ]);

        $reponse = $this->actingAs($attaquant, 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/demander-report", [
                'motif_report' => 'Empêchement',
            ]);

        $reponse->assertStatus(403);
        $this->assertEquals('ENVOYEE', $convocation->fresh()->statut);
    }

    public function test_le_proprietaire_peut_toujours_confirmer_sa_propre_convocation(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();
        $audience->update(['statut' => 'PROGRAMMEE', 'date_heure' => now()->addDays(2)]);
        $convocation = Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE',
        ]);

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/confirmer");

        $reponse->assertOk();
        $this->assertEquals('CONFIRMEE', $convocation->fresh()->statut);

        // Une fois l'audience tenue, la convocation ne se confirme ni ne se reporte plus.
        $audience->update(['statut' => 'CLOTUREE']);
        $this->actingAs($justiciable, 'sanctum')->postJson("/api/convocations/{$convocation->id_convocation}/confirmer")->assertStatus(409);
        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/demander-report", ['motif_report' => 'Empêchement'])
            ->assertStatus(409);
    }

    public function test_transmettre_sans_pv_renvoie_une_erreur_propre_plutot_quun_crash(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $audience = $this->audienceDeTest();

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/pv/transmettre");

        $reponse->assertStatus(404);
    }

    public function test_valider_sans_pv_renvoie_une_erreur_propre_plutot_quun_crash(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest();

        $reponse = $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/pv/valider");

        $reponse->assertStatus(404);

        // Cycle du PV : pas de validation avant transmission, pas de
        // modification une fois validé.
        $greffier = $this->creerUtilisateur('GREFFIER');
        $pv = \App\Models\ProcesVerbal::create(['id_audience' => $audience->id_audience, 'contenu' => 'Brouillon', 'statut' => 'EN_COURS']);
        $this->actingAs($juge, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/pv/valider")->assertStatus(409);
        $pv->update(['statut' => 'CLOTURE']);
        $this->actingAs($greffier, 'sanctum')->putJson("/api/audiences/{$audience->id_audience}/pv", ['contenu' => 'Réécrit'])->assertStatus(409);
        $this->actingAs($greffier, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/pv/transmettre")->assertStatus(409);
        $this->assertEquals('Brouillon', $pv->fresh()->contenu);
    }

    public function test_rejeter_sans_pv_renvoie_une_erreur_propre_plutot_quun_crash(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest();

        $reponse = $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/pv/rejeter", [
                'commentaire' => 'À corriger',
            ]);

        $reponse->assertStatus(404);
    }
}
