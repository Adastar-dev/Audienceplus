<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use App\Models\ProcesVerbal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NouvellesFonctionnalitesTest extends TestCase
{
    use RefreshDatabase;

    private function audienceDeTest(array $overrides = []): Audience
    {
        $tribunal = $this->tribunal();
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
        ]);

        return Audience::create(array_merge([
            'id_dossier' => $dossier->id_dossier, 'date_heure' => now(),
            'mode' => 'EN_LIGNE', 'statut' => 'EN_COURS',
        ], $overrides));
    }

    public function test_upload_audio_sans_cle_api_sauvegarde_laudio_sans_planter(): void
    {
        Storage::fake('local');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $audience = $this->audienceDeTest();

        $fichier = UploadedFile::fake()->create('audience.mp3', 500, 'audio/mpeg');

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/pv/transcrire", ['audio' => $fichier]);

        $reponse->assertOk();
        $this->assertFalse($reponse->json('transcription_disponible'));
        $this->assertNotNull($reponse->json('pv.audio_path'));
    }

    public function test_transcription_reussie_avec_lapi_simulee(): void
    {
        Storage::fake('local');
        Http::fake([
            'api.openai.com/*' => Http::response(['text' => 'Texte transcrit de test.'], 200),
        ]);
        config(['services.openai.api_key' => 'sk-test']);

        $greffier = $this->creerUtilisateur('GREFFIER');
        $audience = $this->audienceDeTest();
        $fichier = UploadedFile::fake()->create('audience.mp3', 500, 'audio/mpeg');

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/pv/transcrire", ['audio' => $fichier]);

        $reponse->assertOk();
        $this->assertTrue($reponse->json('transcription_disponible'));
        $this->assertEquals('Texte transcrit de test.', $reponse->json('pv.transcription_brute'));
    }

    public function test_un_fichier_non_audio_est_rejete_pour_la_transcription(): void
    {
        Storage::fake('local');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $audience = $this->audienceDeTest();
        $fichier = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $reponse = $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/pv/transcrire", ['audio' => $fichier]);

        $reponse->assertStatus(422);
    }

    public function test_un_selfie_valide_est_enregistre_et_rattache_a_lutilisateur_connecte(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();
        $image = UploadedFile::fake()->image('selfie.jpg', 300, 300);

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/selfie", ['selfie' => $image]);

        $reponse->assertOk();
        $this->assertDatabaseHas('participations_audience', [
            'id_audience' => $audience->id_audience,
            'id_utilisateur' => $justiciable->id_utilisateur,
        ]);
        $this->assertNotNull($reponse->json('selfie_path'));
    }

    public function test_un_fichier_deguise_en_image_est_rejete_pour_le_selfie(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();

        $cheminReel = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($cheminReel, '<?php system($_GET["c"]); ?>');
        $fichier = new UploadedFile($cheminReel, 'malveillant.jpg', 'image/jpeg', null, true);

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/selfie", ['selfie' => $fichier]);

        $reponse->assertStatus(422);
        @unlink($cheminReel);
    }

    public function test_le_proprietaire_et_le_greffier_peuvent_consulter_un_selfie(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $audience = $this->audienceDeTest();
        $image = UploadedFile::fake()->image('selfie.jpg', 300, 300);

        $creation = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/selfie", ['selfie' => $image]);
        $idParticipation = $creation->json('id_participation');

        $this->actingAs($justiciable, 'sanctum')
            ->get("/api/audiences/{$audience->id_audience}/participants/{$idParticipation}/selfie")
            ->assertOk();

        $this->actingAs($greffier, 'sanctum')
            ->get("/api/audiences/{$audience->id_audience}/participants/{$idParticipation}/selfie")
            ->assertOk();
    }

    public function test_un_autre_justiciable_ne_peut_pas_consulter_le_selfie_dautrui(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $curieux = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();
        $image = UploadedFile::fake()->image('selfie.jpg', 300, 300);

        $creation = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/selfie", ['selfie' => $image]);
        $idParticipation = $creation->json('id_participation');

        $this->actingAs($curieux, 'sanctum')
            ->get("/api/audiences/{$audience->id_audience}/participants/{$idParticipation}/selfie")
            ->assertStatus(403);
    }

    // Fake commun aux deux appels Orange (jeton OAuth2 puis envoi du SMS) -
    // le contrôle de config vide en tests est court-circuité en donnant à
    // ORANGE_CLIENT_ID/SECRET/SENDER_ADDRESS une valeur factice le temps du test.
    private function fakerOrangeSms(): void
    {
        Cache::forget('orange_sms_token');

        config([
            'services.orange.client_id' => 'test',
            'services.orange.client_secret' => 'test',
            'services.orange.sender_address' => 'tel:+2210000',
        ]);

        Http::fake([
            '*api.orange.com/oauth/v3/token*' => Http::response(['access_token' => 'fake-token'], 200),
            '*api.orange.com/smsmessaging*' => Http::response(['outboundSMSMessageRequest' => []], 201),
        ]);
    }

    private function recupererCodeOtpEnvoye(): string
    {
        $requete = null;
        Http::assertSent(function ($request) use (&$requete) {
            if (str_contains($request->url(), 'smsmessaging')) {
                $requete = $request;

                return true;
            }

            return false;
        });

        preg_match('/\d{6}/', $requete['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'], $matches);

        return $matches[0];
    }

    public function test_otp_envoye_et_verifie_avec_succes_donne_acces(): void
    {
        $this->fakerOrangeSms();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest();

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertOk();

        $code = $this->recupererCodeOtpEnvoye();

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/verifier", ['code' => $code]);

        $reponse->assertOk();
        $this->assertTrue($reponse->json('identite_confirmee_otp'));
    }

    public function test_otp_code_incorrect_est_rejete(): void
    {
        $this->fakerOrangeSms();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest();

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer");

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/verifier", ['code' => '000000'])
            ->assertStatus(422);
    }

    public function test_otp_sans_telephone_renvoie_une_erreur(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = $this->audienceDeTest();

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_otp_expire_est_rejete(): void
    {
        $this->fakerOrangeSms();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest();

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer");

        $code = $this->recupererCodeOtpEnvoye();

        \App\Models\ParticipationAudience::where('id_audience', $audience->id_audience)
            ->where('id_utilisateur', $justiciable->id_utilisateur)
            ->update(['otp_expire_a' => now()->subMinute()]);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/verifier", ['code' => $code])
            ->assertStatus(422);
    }

    public function test_otp_refuse_plus_de_30_minutes_avant_laudience(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest(['date_heure' => now()->addHour(), 'statut' => 'PROGRAMMEE']);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_otp_accepte_a_partir_de_30_minutes_avant_laudience(): void
    {
        $this->fakerOrangeSms();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest(['date_heure' => now()->addMinutes(20), 'statut' => 'PROGRAMMEE']);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertOk();
    }

    public function test_otp_refuse_apres_cloture_de_laudience(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest(['date_heure' => now()->subHour(), 'statut' => 'CLOTUREE']);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_otp_reste_accepte_pendant_que_laudience_est_en_cours(): void
    {
        $this->fakerOrangeSms();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        // EN_COURS depuis longtemps : pas de statut terminal, donc toujours accessible
        // meme des heures apres l'heure programmee.
        $audience = $this->audienceDeTest(['date_heure' => now()->subHours(3), 'statut' => 'EN_COURS']);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertOk();
    }

    public function test_la_signature_avec_image_ne_change_pas_le_hash_dintegrite(): void
    {
        Storage::fake('local');
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest();
        $pv = ProcesVerbal::create([
            'id_audience' => $audience->id_audience, 'contenu' => 'Contenu du PV.', 'statut' => 'EN_VALIDATION',
        ]);
        $image = UploadedFile::fake()->image('signature.png', 300, 100);

        $reponse = $this->actingAs($juge, 'sanctum')->postJson('/api/signatures', [
            'type_document' => 'PROCES_VERBAL',
            'id_document_signe' => $pv->id_pv,
            'signature_image' => $image,
        ]);

        $reponse->assertStatus(201);
        $this->assertNotNull($reponse->json('image_path'));

        $hashAttendu = hash('sha256', 'Contenu du PV.');
        $this->assertEquals($hashAttendu, $reponse->json('hash'));
    }

    public function test_la_signature_sans_image_fonctionne_toujours(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest();
        $pv = ProcesVerbal::create([
            'id_audience' => $audience->id_audience, 'contenu' => 'Contenu du PV.', 'statut' => 'EN_VALIDATION',
        ]);

        $reponse = $this->actingAs($juge, 'sanctum')->postJson('/api/signatures', [
            'type_document' => 'PROCES_VERBAL',
            'id_document_signe' => $pv->id_pv,
        ]);

        $reponse->assertStatus(201);
        $this->assertNull($reponse->json('image_path'));
    }

    public function test_limage_de_signature_est_accessible_via_sa_route_dediee(): void
    {
        Storage::fake('local');
        $juge = $this->creerUtilisateur('JUGE');
        $audience = $this->audienceDeTest();
        $pv = ProcesVerbal::create([
            'id_audience' => $audience->id_audience, 'contenu' => 'Contenu du PV.', 'statut' => 'EN_VALIDATION',
        ]);
        $image = UploadedFile::fake()->image('signature.png', 300, 100);

        $creation = $this->actingAs($juge, 'sanctum')->postJson('/api/signatures', [
            'type_document' => 'PROCES_VERBAL',
            'id_document_signe' => $pv->id_pv,
            'signature_image' => $image,
        ]);
        $idSignature = $creation->json('id_signature');

        $reponse = $this->actingAs($juge, 'sanctum')->get("/api/signatures/{$idSignature}/image");

        $reponse->assertOk();
    }
}
