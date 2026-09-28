<?php

namespace App\Support;

// Numéros stockés au format international (+221XXXXXXXXX), ce qui permet de
// se connecter avec son numéro quelle que soit la saisie. Un numéro sénégalais saisi sans indicatif est complété ;
// un numéro étranger (diaspora) est accepté tel quel s'il commence par + ou 00.
class Telephone
{
    // Format international accepté après normalisation (E.164).
    public const REGLE = 'regex:/^\+[1-9][0-9]{7,14}$/';

    public const MESSAGE = 'Le numéro de téléphone doit être au format international, par exemple +221 77 000 00 00.';

    public static function normaliser(?string $saisie): ?string
    {
        if ($saisie === null) {
            return null;
        }

        $numero = preg_replace('/[\s.\-()]/', '', $saisie);

        // Champ laissé à son préremplissage : aucun numéro.
        if ($numero === '' || $numero === '+' || $numero === '+221') {
            return null;
        }

        if (str_starts_with($numero, '00')) {
            return '+'.substr($numero, 2);
        }

        // Numéro sénégalais saisi sans indicatif (9 chiffres, ex. 77 000 00 00).
        if (preg_match('/^[0-9]{9}$/', $numero)) {
            return '+221'.$numero;
        }

        // Indicatif sénégalais saisi sans le +.
        if (preg_match('/^221[0-9]{9}$/', $numero)) {
            return '+'.$numero;
        }

        return $numero;
    }
}
