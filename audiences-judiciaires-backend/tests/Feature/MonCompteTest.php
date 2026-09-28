<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonCompteTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_compte_cree_par_ladmin_recoit_une_notification_cliquable_pour_deposer_sa_cni(): void
    {
        $admin = $this->creerUtilisateur('ADMINISTRATEUR');

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/utilisateurs', [
            'nom' => 'Awa Diop', 'email' => 'awa@mail.sn', 'role' => 'JUSTICIABLE', 'cni' => '1234567890123',
        ])->assertStatus(201)->json('utilisateur.id_utilisateur');

        $notification = Notification::where('id_utilisateur', $id)->sole();
        $this->assertEquals('Identité à compléter', $notification->type);
        $this->assertEquals('/compte', $notification->lien);
    }

    public function test_la_connexion_rappelle_une_seule_fois_la_cni_manquante(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', [
            'identite_verifiee' => false, 'mot_de_passe' => Hash::make('motdepasse'),
        ]);

        foreach ([1, 2] as $_) {
            $this->postJson('/api/login', ['identifiant' => $justiciable->email, 'mot_de_passe' => 'motdepasse'])->assertOk();
        }

        $this->assertEquals(1, Notification::where('id_utilisateur', $justiciable->id_utilisateur)->where('type', 'Identité à compléter')->count());
    }

    public function test_pas_de_rappel_pour_un_compte_verifie_ou_un_magistrat(): void
    {
        $juge = $this->creerUtilisateur('JUGE', ['mot_de_passe' => Hash::make('motdepasse')]);
        $verifie = $this->creerUtilisateur('AVOCAT', ['mot_de_passe' => Hash::make('motdepasse')]);

        $this->postJson('/api/login', ['identifiant' => $juge->email, 'mot_de_passe' => 'motdepasse'])->assertOk();
        $this->postJson('/api/login', ['identifiant' => $verifie->email, 'mot_de_passe' => 'motdepasse'])->assertOk();

        $this->assertEquals(0, Notification::where('type', 'Identité à compléter')->count());
    }

    public function test_mon_compte_renvoie_les_informations_avec_le_tribunal(): void
    {
        $tribunal = $this->tribunal();
        $greffier = $this->creerUtilisateur('GREFFIER', ['id_tribunal' => $tribunal->id_tribunal]);

        $this->actingAs($greffier, 'sanctum')->getJson('/api/utilisateur')
            ->assertOk()
            ->assertJsonPath('email', $greffier->email)
            ->assertJsonPath('tribunal.nom', $tribunal->nom)
            ->assertJsonMissingPath('mot_de_passe')
            ->assertJsonMissingPath('cni_photo_path');
    }

    public function test_on_peut_completer_un_telephone_manquant_mais_pas_le_modifier(): void
    {
        $greffier = $this->creerUtilisateur('GREFFIER', ['telephone' => null]);

        $this->actingAs($greffier, 'sanctum')->patchJson('/api/mon-compte', ['telephone' => '77 123 45 67'])
            ->assertOk()->assertJsonPath('telephone', '+221771234567');

        $this->actingAs($greffier, 'sanctum')->patchJson('/api/mon-compte', ['telephone' => '+221780000000'])
            ->assertStatus(422)->assertJsonValidationErrors('telephone');
        $this->assertEquals('+221771234567', $greffier->fresh()->telephone);
    }

    public function test_un_justiciable_non_verifie_complete_son_numero_de_cni(): void
    {
        $justiciable = $this->creerUtilisateur('JUSTICIABLE', [
            'identite_verifiee' => false, 'cni' => null, 'numero_cni_detecte' => '1234567890123',
        ]);

        $this->actingAs($justiciable, 'sanctum')->patchJson('/api/mon-compte', ['cni' => '1234567890123'])->assertOk();

        $this->assertTrue($justiciable->fresh()->numero_cni_concorde);
    }

    public function test_pas_de_cni_pour_un_compte_verifie_ni_de_champ_non_autorise(): void
    {
        $avocat = $this->creerUtilisateur('AVOCAT', ['cni' => null]);   // compte vérifié

        $this->actingAs($avocat, 'sanctum')->patchJson('/api/mon-compte', ['cni' => '1234567890123'])
            ->assertStatus(422)->assertJsonValidationErrors('cni');
        $this->actingAs($avocat, 'sanctum')->patchJson('/api/mon-compte', ['nom' => 'Autre nom', 'role' => 'JUGE'])
            ->assertStatus(422);
        $this->assertEquals('AVOCAT', $avocat->fresh()->role);
    }
}
