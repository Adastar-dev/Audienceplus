<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\DemandeDistance;
use App\Models\Dossier;
use App\Models\Notification;
use App\Support\Telephone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TelephoneEtNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_numeros_sont_normalises_au_format_international(): void
    {
        $this->assertSame('+221771234567', Telephone::normaliser('77 123 45 67'));
        $this->assertSame('+221771234567', Telephone::normaliser('+221 77 123 45 67'));
        $this->assertSame('+221771234567', Telephone::normaliser('00221771234567'));
        $this->assertSame('+221771234567', Telephone::normaliser('221771234567'));
        $this->assertSame('+33612345678', Telephone::normaliser('+33 6 12 34 56 78'));
        $this->assertNull(Telephone::normaliser('+221 '));
        $this->assertNull(Telephone::normaliser(''));
    }

    public function test_linscription_enregistre_le_numero_normalise(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->post('/api/inscription', [
            'nom' => 'Awa Diop',
            'email' => 'awa.diop@mail.sn',
            'telephone' => '+221 77 123 45 67',
            'cni' => '1234567890123',
            'mot_de_passe' => 'motdepasse',
            'role' => 'JUSTICIABLE',
            'cni_photo' => \Illuminate\Http\UploadedFile::fake()->image('cni.jpg', 600, 400),
            'cni_verso' => \Illuminate\Http\UploadedFile::fake()->image('cni-verso.jpg', 600, 400),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertDatabaseHas('utilisateurs', ['nom' => 'Awa Diop', 'telephone' => '+221771234567']);
    }

    public function test_on_peut_se_connecter_avec_le_numero_saisi_sans_indicatif(): void
    {
        $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221771234567']);

        $this->postJson('/api/login', ['identifiant' => '77 123 45 67', 'mot_de_passe' => 'password'])
            ->assertOk();
    }

    public function test_ladministrateur_peut_donner_un_numero_a_un_juge(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $reponse = $this->actingAs($admin, 'sanctum')->postJson('/api/utilisateurs', [
            'nom' => 'Juge Ndiaye',
            'email' => 'juge.ndiaye@justice.sn',
            'role' => 'JUGE',
            'telephone' => '78 111 22 33',
        ]);

        $reponse->assertStatus(201);
        $this->assertSame('+221781112233', $reponse->json('utilisateur.telephone'));

        $idJuge = $reponse->json('utilisateur.id_utilisateur');
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/utilisateurs/{$idJuge}", ['telephone' => '+221 76 000 00 01'])
            ->assertOk()
            ->assertJsonPath('telephone', '+221760000001');
    }

    public function test_un_numero_invalide_est_refuse(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $this->actingAs($admin, 'sanctum')->postJson('/api/utilisateurs', [
            'nom' => 'Greffier', 'email' => 'g@justice.sn', 'role' => 'GREFFIER', 'telephone' => 'abc',
        ])->assertStatus(422)->assertJsonValidationErrors('telephone');
    }

    private function dossierAvecAudience(array $audience = []): array
    {
        $tribunal = $this->tribunal();
        $procureur = $this->creerUtilisateur('PROCUREUR');
        $dossier = Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'ADOPTION', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $tribunal->id_tribunal, 'date_creation' => now(),
            'id_procureur' => $procureur->id_utilisateur,
        ]);
        $juge = $this->creerUtilisateur('JUGE');
        $aud = Audience::create(array_merge([
            'id_dossier' => $dossier->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->addDays(2), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ], $audience));

        return [$dossier, $aud, $juge, $procureur];
    }

    public function test_le_juge_et_le_procureur_sont_prevenus_de_la_programmation(): void
    {
        Mail::fake();
        [$dossier, , $juge, $procureur] = $this->dossierAvecAudience();
        $greffier = $this->creerUtilisateur('GREFFIER');

        $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $dossier->id_dossier,
            'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->addDays(5)->toDateTimeString(),
        ])->assertStatus(201);

        $this->assertTrue(Notification::where('id_utilisateur', $juge->id_utilisateur)->where('type', 'Audience programmée')->exists());
        $this->assertTrue(Notification::where('id_utilisateur', $procureur->id_utilisateur)->where('type', 'Audience programmée')->exists());
    }

    public function test_le_greffier_puis_le_juge_sont_prevenus_dune_demande_de_report(): void
    {
        Mail::fake();
        [, $audience, $juge] = $this->dossierAvecAudience();
        $greffier = $this->creerUtilisateur('GREFFIER');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $convocation = Convocation::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'canal' => 'EMAIL', 'statut' => 'ENVOYEE', 'date_envoi' => now(),
        ]);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/demander-report", ['motif_report' => 'Hospitalisation'])
            ->assertOk();
        $this->assertTrue(Notification::where('id_utilisateur', $greffier->id_utilisateur)->where('type', 'Demande de report')->exists());

        $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/convocations/{$convocation->id_convocation}/avis-report", [
                'avis' => 'FAVORABLE', 'nouvelle_date_heure' => now()->addDays(10)->toDateTimeString(),
            ])->assertOk();
        $this->assertTrue(Notification::where('id_utilisateur', $juge->id_utilisateur)->where('type', 'Report à décider')->exists());
    }

    public function test_le_code_otp_napparait_jamais_dans_les_notifications_de_la_plateforme(): void
    {
        Mail::fake();
        [, $audience] = $this->dossierAvecAudience(['statut' => 'EN_COURS', 'date_heure' => now()]);
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', ['telephone' => '+221771234567']);
        $participation = \App\Models\ParticipationAudience::create([
            'id_audience' => $audience->id_audience,
            'id_utilisateur' => $justiciable->id_utilisateur,
            'role_audience' => 'JUSTICIABLE',
        ]);

        app(\App\Services\OtpService::class)->envoyer($participation);

        $notification = Notification::where('id_utilisateur', $justiciable->id_utilisateur)->firstOrFail();
        $this->assertDoesNotMatchRegularExpression('/\d{6}/', $notification->message);
    }

    public function test_le_greffier_puis_le_juge_sont_prevenus_dune_demande_a_distance(): void
    {
        Mail::fake();
        [, $audience, $juge] = $this->dossierAvecAudience();
        $greffier = $this->creerUtilisateur('GREFFIER');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');

        $this->actingAs($justiciable, 'sanctum')
            ->postJson('/api/demandes-distance', ['id_audience' => $audience->id_audience, 'motif' => 'Réside à Paris'])
            ->assertStatus(201);
        $this->assertTrue(Notification::where('id_utilisateur', $greffier->id_utilisateur)->where('type', 'Demande à distance')->exists());

        $demande = DemandeDistance::first();
        $this->actingAs($greffier, 'sanctum')
            ->postJson("/api/demandes-distance/{$demande->id_demande}/avis", ['avis' => 'FAVORABLE'])
            ->assertOk();
        $this->assertTrue(Notification::where('id_utilisateur', $juge->id_utilisateur)->where('type', 'Demande à distance à décider')->exists());
    }
}
