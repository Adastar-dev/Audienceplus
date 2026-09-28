<?php

namespace Tests;

use App\Models\Tribunal;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    // Aucun appel HTTP réel pendant les tests (API d'IA, Jitsi...) : un test
    // qui n'a pas simulé ses appels avec Http::fake() échoue au lieu
    // d'appeler le vrai service.
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    // Garde-fou : RefreshDatabase efface toutes les tables. On refuse de lancer
    // un test si la connexion n'est pas SQLite en mémoire, pour ne jamais
    // toucher la base MySQL de développement.
    public function createApplication()
    {
        $app = parent::createApplication();

        $connexion = $app['config']->get('database.default');
        $base = $app['config']->get("database.connections.{$connexion}.database");

        if ($connexion !== 'sqlite' || $base !== ':memory:') {
            throw new \RuntimeException(
                "Tests interrompus : la base configurée est {$connexion} ({$base}) au lieu de SQLite en mémoire."
            );
        }

        return $app;
    }

    protected function tribunal(array $overrides = []): Tribunal
    {
        return Tribunal::create(array_merge([
            'nom' => 'Tribunal de Grande Instance de Dakar',
            'ville' => 'Dakar',
        ], $overrides));
    }

    protected function creerUtilisateur(string $role, array $overrides = []): Utilisateur
    {
        static $compteur = 0;
        $compteur++;

        return Utilisateur::create(array_merge([
            'nom' => "Utilisateur Test {$compteur}",
            'email' => "test{$compteur}@justice.sn",
            'mot_de_passe' => Hash::make('password'),
            'role' => $role,
            'identite_verifiee' => true,
        ], $overrides));
    }
}
