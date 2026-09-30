<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Dossier;
use App\Models\ProcesVerbal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'api.groq.com/*' => Http::response(['text' => 'Texte transcrit de test.'], 200),
        ]);
        config(['services.ia.api_key' => 'sk-test']);

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

    private function deposerCni(\App\Models\Utilisateur $utilisateur, UploadedFile $fichier)
    {
        return $this->actingAs($utilisateur, 'sanctum')
            ->post('/api/mon-identite/cni', [
                'cni_photo' => $fichier,
                'cni_verso' => UploadedFile::fake()->image('cni-verso.jpg', 300, 300),
            ], ['Accept' => 'application/json']);
    }

    public function test_une_photo_de_cni_valide_est_enregistree_et_rattachee_a_lutilisateur_connecte(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);

        $this->deposerCni($justiciable, UploadedFile::fake()->image('cni.jpg', 300, 300))
            ->assertOk()
            ->assertJsonPath('a_photo_cni', true);

        $this->assertNotNull($justiciable->fresh()->cni_photo_path);
    }

    public function test_un_fichier_deguise_en_image_est_rejete_pour_la_cni(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);

        $cheminReel = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($cheminReel, '<?php system($_GET["c"]); ?>');
        $fichier = new UploadedFile($cheminReel, 'malveillant.jpg', 'image/jpeg', null, true);

        $this->deposerCni($justiciable, $fichier)->assertStatus(422);
        @unlink($cheminReel);
    }

    public function test_un_compte_verifie_ne_peut_plus_changer_sa_photo_de_cni(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => true]);

        $this->deposerCni($justiciable, UploadedFile::fake()->image('cni.jpg', 300, 300))->assertStatus(422);
    }

    public function test_ladministrateur_peut_consulter_la_photo_de_cni(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');
        $this->deposerCni($justiciable, UploadedFile::fake()->image('cni.jpg', 300, 300))->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->get("/api/utilisateurs/{$justiciable->id_utilisateur}/cni")
            ->assertOk();
    }

    public function test_le_verso_seul_ne_suffit_pas_et_ladministrateur_voit_le_verso(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $this->actingAs($justiciable, 'sanctum')
            ->post('/api/mon-identite/cni', ['cni_photo' => UploadedFile::fake()->image('cni.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('cni_verso');
        $this->assertFalse($justiciable->fresh()->a_photo_cni);

        $this->deposerCni($justiciable, UploadedFile::fake()->image('cni.jpg', 300, 300))->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->get("/api/utilisateurs/{$justiciable->id_utilisateur}/cni?face=verso")
            ->assertOk();
    }

    public function test_un_autre_role_ne_peut_pas_consulter_la_cni_dautrui(): void
    {
        Storage::fake('local');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['identite_verifiee' => false]);
        $greffier = $this->creerUtilisateur('GREFFIER');
        $this->deposerCni($justiciable, UploadedFile::fake()->image('cni.jpg', 300, 300))->assertOk();

        $this->actingAs($greffier, 'sanctum')
            ->getJson("/api/utilisateurs/{$justiciable->id_utilisateur}/cni")
            ->assertStatus(403);
    }

    private function recupererCodeOtpEnvoye(): string
    {
        $message = app('mailer')->getSymfonyTransport()->messages()->last();
        preg_match('/\d{6}/', $message->getOriginalMessage()->getTextBody(), $matches);

        return $matches[0];
    }

    public function test_otp_envoye_et_verifie_avec_succes_donne_acces(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest();
        $this->lierPartie($audience->dossier, $justiciable);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertOk()
            ->assertJsonPath('message', 'Code envoyé par email.');

        // Le code part uniquement par email : aucun appel à une API de SMS.
        Http::assertNothingSent();
        $code = $this->recupererCodeOtpEnvoye();

        $reponse = $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/verifier", ['code' => $code]);

        $reponse->assertOk();
        $this->assertTrue($reponse->json('identite_confirmee_otp'));
    }

    public function test_otp_code_incorrect_est_rejete(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest();
        $this->lierPartie($audience->dossier, $justiciable);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer");

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/verifier", ['code' => '000000'])
            ->assertStatus(422);
    }

    public function test_otp_sans_email_renvoie_une_erreur(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['email' => null, 'telephone' => '+221781705348']);
        $audience = $this->audienceDeTest();
        $this->lierPartie($audience->dossier, $justiciable);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_otp_expire_est_rejete(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest();
        $this->lierPartie($audience->dossier, $justiciable);

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
        $this->lierPartie($audience->dossier, $justiciable);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_otp_accepte_a_partir_de_30_minutes_avant_laudience(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest(['date_heure' => now()->addMinutes(20), 'statut' => 'PROGRAMMEE']);
        $this->lierPartie($audience->dossier, $justiciable);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertOk();
    }

    public function test_otp_refuse_apres_cloture_de_laudience(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        $audience = $this->audienceDeTest(['date_heure' => now()->subHour(), 'statut' => 'CLOTUREE']);
        $this->lierPartie($audience->dossier, $justiciable);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_otp_reste_accepte_pendant_que_laudience_est_en_cours(): void
    {
        Http::fake();
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221781705348']);
        // EN_COURS depuis longtemps : pas de statut terminal, donc toujours accessible
        // meme des heures apres l'heure programmee.
        $audience = $this->audienceDeTest(['date_heure' => now()->subHours(3), 'statut' => 'EN_COURS']);
        $this->lierPartie($audience->dossier, $justiciable);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/envoyer")
            ->assertOk();
    }

    public function test_la_signature_scelle_le_contenu_du_pv_par_son_empreinte(): void
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
        $this->assertEquals(hash('sha256', 'Contenu du PV.'), $reponse->json('hash'));
    }
}
