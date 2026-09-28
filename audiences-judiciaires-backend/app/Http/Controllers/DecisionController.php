<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Support\CodeQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

// Décision rendue à l'issue d'une audience, téléchargeable en PDF par les
// personnes qui ont accès au dossier. Le QR code porte la référence de la
// décision et une empreinte calculée avec la clé de l'application : toute
// modification du contenu imprimé la rend incohérente.
class DecisionController extends Controller
{
    public const LIBELLES = [
        'JUGEMENT' => 'Jugement',
        'RENVOI' => 'Renvoi',
        'DELIBERE' => 'Mise en délibéré',
    ];

    public function pdf(Request $request, Audience $audience)
    {
        $dossier = $audience->dossier;

        if (! $dossier->estAccessiblePar($request->user())) {
            return response()->json(['message' => "Vous n'avez pas accès à cette décision."], 403);
        }

        // Jugement (audience close), renvoi ou mise en délibéré : dans les trois
        // cas la décision est rendue dès que l'audience n'est plus en cours.
        if (! $audience->type_decision || $audience->statut === 'EN_COURS') {
            return response()->json(['message' => "Aucune décision n'a encore été rendue pour cette audience."], 404);
        }

        $audience->load(['juge', 'procesVerbal']);
        $dossier->load('tribunal');

        $reference = self::reference($audience);
        $empreinte = self::empreinte($audience);

        $pdf = Pdf::loadView('pdf.decision', [
            'audience' => $audience,
            'dossier' => $dossier,
            'libelle' => self::LIBELLES[$audience->type_decision] ?? $audience->type_decision,
            'reference' => $reference,
            'empreinte' => $empreinte,
            'qr' => CodeQr::dataUri("Audience+ - Décision {$reference} - Dossier {$dossier->numero} - Empreinte {$empreinte}"),
            'pvValide' => $audience->procesVerbal?->statut === 'CLOTURE',
        ]);

        return $pdf->download("decision-{$reference}.pdf");
    }

    public static function reference(Audience $audience): string
    {
        return sprintf('AJ-DEC-%s-%06d', $audience->date_heure->format('Y'), $audience->id_audience);
    }

    public static function empreinte(Audience $audience): string
    {
        $contenu = implode('|', [
            $audience->id_audience,
            $audience->dossier->numero,
            $audience->type_decision,
            $audience->motif_decision,
            $audience->date_heure->toIso8601String(),
        ]);

        return strtoupper(substr(hash_hmac('sha256', $contenu, config('app.key')), 0, 16));
    }
}
