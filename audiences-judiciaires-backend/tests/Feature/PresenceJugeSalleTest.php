<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\DemandeDistance;
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
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur, 'juge_connecte' => true, 'type_decision' => 'JUGEMENT']);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/fermer")
            ->assertOk();

        $this->assertFalse($audience->fresh()->juge_connecte);
    }

    public function test_une_audience_ne_se_ferme_pas_sans_decision(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur]);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/fermer")
            ->assertStatus(422);

        $this->assertSame('EN_COURS', $audience->fresh()->statut);
    }

    public function test_un_justiciable_etranger_au_dossier_ne_lit_pas_le_pv(): void
    {
        $audience = $this->audienceDeTest();
        \App\Models\ProcesVerbal::create(['id_audience' => $audience->id_audience, 'contenu' => 'PV confidentiel.', 'statut' => 'CLOTURE']);
        $curieux = $this->creerUtilisateur('JUSTICIABLE');

        $this->actingAs($curieux, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/pv")
            ->assertStatus(403);
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

    private function justiciablePartieA(Audience $audience): \App\Models\Utilisateur
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        \App\Models\PartieDossier::create([
            'id_dossier' => $audience->id_dossier,
            'id_utilisateur' => $justiciable->id_utilisateur,
            'role_partie' => 'DEMANDEUR',
        ]);

        return $justiciable;
    }

    public function test_un_justiciable_sans_otp_confirme_na_pas_de_jeton(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $audience = $this->audienceDeTest(['juge_connecte' => true]);
        $justiciable = $this->justiciablePartieA($audience);

        $this->actingAs($justiciable, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertStatus(403);
    }

    public function test_un_justiciable_avec_otp_confirme_recoit_un_jeton(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $audience = $this->audienceDeTest(['juge_connecte' => true]);
        $justiciable = $this->justiciablePartieA($audience);
        \App\Models\ParticipationAudience::create([
            'id_audience' => $audience->id_audience,
            'id_utilisateur' => $justiciable->id_utilisateur,
            'role_audience' => 'JUSTICIABLE',
            'identite_confirmee_otp' => true,
        ]);

        // OTP confirmé mais pas encore admis : il attend (409).
        $this->actingAs($justiciable, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertStatus(409)
            ->assertJsonPath('en_attente_admission', true);

        // Le greffier l'admet : il reçoit son jeton.
        $greffier = $this->creerUtilisateur('GREFFIER');
        $participation = \App\Models\ParticipationAudience::where('id_utilisateur', $justiciable->id_utilisateur)->firstOrFail();
        $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/participants/{$participation->id_participation}/admettre")
            ->assertOk();

        $this->actingAs($justiciable, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertOk()
            ->assertJsonPath('moderateur', false);
    }

    public function test_un_avocat_partie_au_dossier_doit_aussi_confirmer_son_otp(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $audience = $this->audienceDeTest(['juge_connecte' => true]);
        $avocat = $this->creerUtilisateur('AVOCAT');
        \App\Models\PartieDossier::create([
            'id_dossier' => $audience->id_dossier, 'id_utilisateur' => $avocat->id_utilisateur, 'role_partie' => 'DEMANDEUR',
        ]);

        $this->actingAs($avocat, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertStatus(403);
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

    public function test_pas_de_salle_virtuelle_pour_une_audience_en_presentiel_sans_comparution_a_distance(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest(['id_juge' => $juge->id_utilisateur, 'mode' => 'PRESENTIEL']);

        $this->actingAs($juge, 'sanctum')->getJson("/api/audiences/{$audience->id_audience}")
            ->assertOk()->assertJsonPath('salle_virtuelle', false);
        $this->actingAs($juge, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/juge-connecte")
            ->assertStatus(409);
        $this->actingAs($juge, 'sanctum')->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertStatus(409);

        // Une demande seulement en attente ou avec l'avis du greffier ne suffit pas.
        $demande = DemandeDistance::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $this->creerUtilisateur('JUSTICIABLE')->id_utilisateur,
            'motif' => 'Réside à Paris', 'statut' => 'AVIS_GREFFIER_FAVORABLE', 'date_demande' => now(),
        ]);
        $this->actingAs($juge, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/juge-connecte")
            ->assertStatus(409);

        // Comparution à distance accordée par le juge : la salle s'ouvre.
        $demande->update(['statut' => 'APPROUVEE']);
        $this->actingAs($juge, 'sanctum')->postJson("/api/audiences/{$audience->id_audience}/juge-connecte")
            ->assertOk()->assertJsonPath('salle_virtuelle', true);
        $this->actingAs($juge, 'sanctum')->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertOk()->assertJsonPath('moderateur', true);
    }

    public function test_seule_la_partie_dont_la_demande_est_approuvee_rejoint_une_audience_en_presentiel(): void
    {
        config(['services.jitsi.app_secret' => 'secret-de-test']);
        $audience = $this->audienceDeTest(['mode' => 'PRESENTIEL', 'juge_connecte' => true]);
        $aDistance = $this->creerUtilisateur('JUSTICIABLE');
        $auTribunal = $this->creerUtilisateur('JUSTICIABLE');
        $this->lierPartie($audience->dossier, $aDistance);
        $this->lierPartie($audience->dossier, $auTribunal, 'DEFENDEUR');
        DemandeDistance::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $aDistance->id_utilisateur,
            'motif' => 'Réside à Paris', 'statut' => 'APPROUVEE', 'date_demande' => now(),
        ]);

        // L'autre partie, attendue au tribunal, ne reçoit ni code ni jeton.
        $this->actingAs($auTribunal, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertStatus(403);
        $this->actingAs($auTribunal, 'sanctum')
            ->getJson("/api/audiences/{$audience->id_audience}/jitsi-jeton")
            ->assertStatus(403);

        // La partie autorisée reçoit bien son code.
        $this->actingAs($aDistance, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertOk();
    }
}
