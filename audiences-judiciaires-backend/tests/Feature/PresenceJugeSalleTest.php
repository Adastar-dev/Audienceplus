<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceJugeSalleTest extends TestCase
{
    use RefreshDatabase;

    private function audienceDeTest(array $overrides = []): Audience
    {
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);

        return Audience::create(array_merge([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now(),
            'mode' => 'EN_LIGNE', 'statut' => 'EN_COURS',
        ], $overrides));
    }

    public function test_le_juge_signale_sa_presence_puis_son_depart(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur]);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/juge-connecte")
            ->assertOk()
            ->assertJsonPath('juge_connecte', true);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/juge-deconnecte")
            ->assertOk()
            ->assertJsonPath('juge_connecte', false);
    }

    public function test_un_participant_ne_peut_pas_se_faire_passer_pour_le_juge(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT');
        $audience = $this->audienceDeTest();

        $this->actingAs($avocat, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/juge-connecte")
            ->assertStatus(403);

        $this->assertFalse($audience->fresh()->juge_connecte);
    }

    public function test_un_autre_juge_ne_peut_pas_ouvrir_la_salle(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $autreJuge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur]);

        $this->actingAs($autreJuge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/juge-connecte")
            ->assertStatus(403);
    }

    public function test_la_salle_ne_souvre_pas_avant_louverture_de_laudience(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur, 'statut' => 'PROGRAMMEE']);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/juge-connecte")
            ->assertStatus(409);
    }

    public function test_la_cloture_ferme_la_salle(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur, 'juge_connecte' => true]);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/fermer")
            ->assertOk();

        $this->assertFalse($audience->fresh()->juge_connecte);
    }

    private function lireJwt(string $jwt): array
    {
        [, $payload] = explode('.', $jwt);

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }

    public function test_le_juge_recoit_un_jeton_jitsi_moderateur(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test', 'services.jitsi.domain' => 'localhost:8443']);
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur]);

        $reponse = $this->actingAs($juge, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertOk()
            ->assertJsonPath('domaine', 'localhost:8443')
            ->assertJsonPath('salle', "aj-audience-{$audience->id_audience}");

        $claims = $this->lireJwt($reponse->json('jwt'));
        $this->assertTrue($claims['context']['user']['moderator']);
        $this->assertSame("aj-audience-{$audience->id_audience}", $claims['room']);
    }

    public function test_un_participant_na_pas_de_jeton_avant_le_juge(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $greffier = $this->creerUtilisateur('GREFFIER');
        $audience = $this->audienceDeTest();

        $this->actingAs($greffier, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertStatus(409);
    }

    public function test_un_participant_recoit_un_jeton_non_moderateur_une_fois_le_juge_entre(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $greffier = $this->creerUtilisateur('GREFFIER');
        $audience = $this->audienceDeTest(['juge_connecte' => true]);

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertOk()
            ->assertJsonPath('moderateur', false);

        $this->assertFalse($this->lireJwt($reponse->json('jwt'))['context']['user']['moderator']);
    }

    public function test_un_autre_juge_recoit_un_jeton_non_moderateur(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $juge = $this->creerUtilisateur('JUGE');
        $autreJuge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur, 'juge_connecte' => true]);

        $this->actingAs($autreJuge, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertOk()
            ->assertJsonPath('moderateur', false);
    }

    public function test_un_avocat_etranger_au_dossier_na_pas_de_jeton(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $avocat = $this->creerUtilisateur('AVOCAT');
        $audience = $this->audienceDeTest(['juge_connecte' => true]);

        $this->actingAs($avocat, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertStatus(403);
    }

    public function test_le_greffier_ne_peut_pas_programmer_une_audience_en_ligne(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER');
        $dossier = $this->audienceDeTest()->dossier;

        $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $dossier->id_dossier,
            'date_heure' => now()->addDay()->toDateTimeString(),
            'mode' => 'EN_LIGNE',
        ])->assertStatus(422);

        $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $dossier->id_dossier,
            'date_heure' => now()->addDay()->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('mode', 'PRESENTIEL');
    }

    public function test_la_mise_en_delibere_ferme_la_salle(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur, 'juge_connecte' => true]);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/decider", ['type' => 'DELIBERE'])
            ->assertOk()
            ->assertJsonPath('statut', 'DELIBERE');

        $this->assertFalse($audience->fresh()->juge_connecte);
    }

    public function test_un_jugement_puis_la_fermeture_cloturent_laudience_et_le_dossier(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur, 'juge_connecte' => true]);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/decider", ['type' => 'JUGEMENT'])
            ->assertOk();
        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/fermer")
            ->assertOk()
            ->assertJsonPath('statut', 'CLOTUREE');

        $audience->refresh();
        $this->assertFalse($audience->juge_connecte);
        $this->assertSame('JUGE', $audience->dossier->statut);
    }
}
