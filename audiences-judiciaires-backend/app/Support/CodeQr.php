<?php

namespace App\Support;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// QR code en image PNG encodée (data URI), utilisable dans une page web comme
// dans un PDF (DomPDF).
class CodeQr
{
    public static function dataUri(string $contenu): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 6,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($contenu);
    }
}
