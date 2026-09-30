<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\Signature;
use App\Support\CodeQr;
use App\Support\Verification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

// Décision rendue à l'issue d'une audience, téléchargeable en PDF par les
// personnes qui ont accès au dossier. Le QR code mène à la page publique de
// vérification, avec la référence de la décision et une empreinte calculée
// avec la clé de l'application : toute modification de la décision enregistrée
// la rend incohérente avec celle imprimée.
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

        $audience->load(['juge', 'procesVerbal', 'participations.utilisateur']);
        $dossier->load(['tribunal', 'liaisonsParties.utilisateur', 'procureurQuiADonneAvis']);

        $reference = self::reference($audience);
        $empreinte = self::empreinte($audience);
        $urlVerification = Verification::url($reference, $empreinte);
        $pv = $audience->procesVerbal;

        // Parties telles qu'enregistrées (« A c. B » ou « Requête de A »),
        // complétées des avocats rattachés au dossier.
        [$demandeur, $defendeur] = str_contains($dossier->parties, ' c. ')
            ? explode(' c. ', $dossier->parties, 2)
            : [preg_replace('/^Requête de /', '', $dossier->parties), null];
        $avocats = fn (string $role) => $dossier->liaisonsParties
            ->filter(fn ($l) => $l->role_partie === $role && $l->utilisateur?->role === 'AVOCAT')
            ->map(fn ($l) => $l->utilisateur->nom)->implode(', ');

        $aDistance = $audience->demandeDistances()->where('statut', 'APPROUVEE')->pluck('id_utilisateur')->all();
        $presents = $audience->participations
            ->filter(fn ($p) => $p->present && $p->role_audience !== 'JUGE')
            ->map(fn ($p) => [
                'nom' => $p->utilisateur?->nom,
                'qualite' => ucfirst(strtolower($p->role_audience)),
                'modalite' => in_array($p->id_utilisateur, $aDistance) ? 'À distance' : 'Au tribunal',
            ])->values();

        $pdf = Pdf::loadView('pdf.decision', [
            'audience' => $audience,
            'dossier' => $dossier,
            'libelle' => self::LIBELLES[$audience->type_decision] ?? $audience->type_decision,
            'reference' => $reference,
            'empreinte' => $empreinte,
            'qr' => CodeQr::dataUri($urlVerification),
            'urlVerification' => $urlVerification,
            'demandeur' => trim($demandeur),
            'defendeur' => $defendeur ? trim($defendeur) : null,
            'avocatDemandeur' => $avocats('DEMANDEUR'),
            'avocatDefendeur' => $avocats('DEFENDEUR'),
            'presents' => $presents,
            'hybride' => $aDistance !== [],
            'avisParquet' => $dossier->avis_procureur,
            'procureur' => $dossier->procureurQuiADonneAvis?->nom,
            'pv' => $pv?->statut === 'CLOTURE' ? $pv : null,
            'scellement' => $pv ? Signature::where('type_document', 'PROCES_VERBAL')
                ->where('id_document_signe', $pv->id_pv)->latest('date_signature')->first() : null,
        ]);

        // Police intégrée partiellement : un PDF de quelques dizaines de Ko au lieu de 1,5 Mo.
        return $pdf->setOption('enable_font_subsetting', true)->download("decision-{$reference}.pdf");
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
