<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\DemandeDistance;
use App\Models\Dossier;
use App\Models\LogActivite;
use App\Models\Notification;
use App\Models\ParticipationAudience;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibiliteEtSuiviTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(): Dossier
    {
        return Dossier::create([
            'numero' => 'TRB-TEST-'.uniqid(), 'type' => 'DIVORCE', 'statut' => 'EN_COURS',
            'parties' => 'X c. Y', 'id_tribunal' => $this->tribunal()->id_tribunal, 'date_creation' => now(),
        ]);
    }

    public function test_programmer_sur_un_creneau_pris_propose_des_creneaux_libres(): void
    {
        // Lundi 10 h, pour des créneaux ouvrables prévisibles.
        $this->travelTo(Carbon::parse('next monday 07:00'));
        $juge = $this->creerUtilisateur('JUGE');
        $greffier = $this->creerUtilisateur('GREFFIER');
        Audience::create([
            'id_dossier' => $this->dossier()->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->setTime(10, 0), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);

        $reponse = $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $this->dossier()->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->setTime(10, 30)->toDateTimeString(),
        ])->assertStatus(409);

        $this->assertEquals(
            [now()->setTime(11, 0)->toDateTimeString(), now()->setTime(12, 0)->toDateTimeString(), now()->setTime(13, 0)->toDateTimeString()],
            $reponse->json('creneaux_libres'),
        );
        $this->assertEquals(1, Audience::count());

        // Une heure plus tard, le créneau est libre.
        $this->actingAs($greffier, 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $this->dossier()->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->setTime(11, 0)->toDateTimeString(),
        ])->assertStatus(201);
    }

    public function test_une_audience_terminee_ne_bloque_pas_le_creneau(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $date = now()->addDays(3)->setTime(10, 0);
        Audience::create([
            'id_dossier' => $this->dossier()->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => $date, 'mode' => 'PRESENTIEL', 'statut' => 'RENVOYEE',
        ]);

        $this->actingAs($this->creerUtilisateur('GREFFIER'), 'sanctum')->postJson('/api/audiences', [
            'id_dossier' => $this->dossier()->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => $date->toDateTimeString(),
        ])->assertStatus(201);
    }

    public function test_le_greffier_est_prevenu_de_la_decision_du_juge_sur_une_demande_a_distance(): void
    {
        $juge = $this->creerUtilisateur('JUGE');
        $greffier = $this->creerUtilisateur('GREFFIER');
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = Audience::create([
            'id_dossier' => $this->dossier()->id_dossier, 'id_juge' => $juge->id_utilisateur,
            'date_heure' => now()->addDays(3), 'mode' => 'PRESENTIEL', 'statut' => 'PROGRAMMEE',
        ]);
        $demande = DemandeDistance::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'motif' => 'Réside à Paris', 'statut' => 'AVIS_GREFFIER_FAVORABLE', 'date_demande' => now(),
        ]);

        $this->actingAs($juge, 'sanctum')
            ->postJson("/api/demandes-distance/{$demande->id_demande}/refuser", ['commentaire' => 'Présence nécessaire'])
            ->assertOk();

        $this->assertTrue(Notification::where('id_utilisateur', $greffier->id_utilisateur)
            ->where('type', 'Comparution à distance refusée')->exists());
    }

    public function test_un_code_otp_refuse_est_journalise(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE');
        $audience = Audience::create([
            'id_dossier' => $this->dossier()->id_dossier, 'date_heure' => now(),
            'mode' => 'EN_LIGNE', 'statut' => 'EN_COURS',
        ]);
        ParticipationAudience::create([
            'id_audience' => $audience->id_audience, 'id_utilisateur' => $justiciable->id_utilisateur,
            'role_audience' => 'JUSTICIABLE', 'otp_code_hash' => bcrypt('123456'), 'otp_expire_a' => now()->addMinutes(5),
        ]);

        $this->actingAs($justiciable, 'sanctum')
            ->postJson("/api/audiences/{$audience->id_audience}/verification-identite/otp/verifier", ['code' => '000000'])
            ->assertStatus(422);

        $this->assertStringStartsWith('Code de vérification refusé (invalide)', LogActivite::sole()->action);
    }
}
