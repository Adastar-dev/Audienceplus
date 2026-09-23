<?php

namespace Tests;

use App\Models\Tribunal;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
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
