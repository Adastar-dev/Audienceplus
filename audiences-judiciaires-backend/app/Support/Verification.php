<?php

namespace App\Support;

// Adresse publique de vérification d'un document (décision ou extrait de
// casier), encodée dans son QR code : en la scannant, n'importe qui peut
// contrôler que la référence existe et que l'empreinte imprimée est la bonne.
class Verification
{
    public static function url(string $reference, ?string $empreinte = null): string
    {
        $url = rtrim(config('app.frontend_url'), '/').'/verification/'.rawurlencode($reference);

        return $empreinte ? $url.'?e='.rawurlencode($empreinte) : $url;
    }
}
