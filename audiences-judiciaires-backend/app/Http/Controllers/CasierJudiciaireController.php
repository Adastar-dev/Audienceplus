<?php

namespace App\Http\Controllers;

use App\Models\CasierJudiciaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CasierJudiciaireController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->casiersJudiciaires()->latest('date_demandee')->get()
        );
    }

    public function store(Request $request)
    {
        // Même exigence que pour l'accès aux dossiers : l'identité (photo de
        // la CNI) doit avoir été vérifiée par l'administration.
        if (! $request->user()->identite_verifiee) {
            return response()->json([
                'message' => "Votre identité doit d'abord être vérifiée par l'administration avant de demander un extrait.",
            ], 403);
        }

        $casier = CasierJudiciaire::create([
            'id_utilisateur' => $request->user()->id_utilisateur,
            'resultat' => 'VIERGE',
            'date_demandee' => now(),
        ]);

        // Référence unique encodée dans le QR code : l'identifiant du casier
        // (unique) suivi d'un suffixe aléatoire, pour qu'elle ne soit pas
        // devinable. La colonne est aussi unique en base.
        $casier->update([
            'qr_code' => sprintf('AJ-CJ-%s-%06d-%s', now()->format('Y'), $casier->id_casier, strtoupper(Str::random(4))),
        ]);

        return response()->json($casier->fresh(), 201);
    }

    // Extrait en PDF, avec le QR code de sa référence unique.
    public function pdf(Request $request, CasierJudiciaire $casier)
    {
        if ($casier->id_utilisateur !== $request->user()->id_utilisateur) {
            return response()->json(['message' => "Cet extrait ne vous appartient pas."], 403);
        }

        return Pdf::loadView('pdf.casier', [
            'casier' => $casier,
            'titulaire' => $request->user(),
        ])->setOption('enable_font_subsetting', true)->download("casier-{$casier->qr_code}.pdf");
    }
}
