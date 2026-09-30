<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\CasierJudiciaire;
use Illuminate\Http\Request;

// Vérification publique (sans connexion) d'un document délivré par la
// plateforme, à partir de la référence lue dans son QR code. Seules les
// informations nécessaires au contrôle sont renvoyées : ni le nom des
// parties d'une décision, ni l'identité complète du titulaire d'un extrait.
class VerificationController extends Controller
{
    public function show(Request $request, string $reference)
    {
        $reference = strtoupper(trim($reference));

        if (preg_match('/^AJ-DEC-(\d{4})-(\d{6})$/', $reference, $m)) {
            return $this->decision($request, $reference, (int) $m[2]);
        }

        if (preg_match('/^AJ-CJ-\d{4}-\d{6}-[A-Z0-9]{4}$/', $reference)) {
            return $this->casier($reference);
        }

        return $this->inconnu($reference);
    }

    private function decision(Request $request, string $reference, int $idAudience)
    {
        $audience = Audience::with('dossier.tribunal')->find($idAudience);

        if (! $audience || ! $audience->type_decision || $audience->statut === 'EN_COURS'
            || DecisionController::reference($audience) !== $reference) {
            return $this->inconnu($reference);
        }

        $empreinte = DecisionController::empreinte($audience);
        $empreinteLue = $request->query('e');

        return response()->json([
            'authentique' => true,
            'type' => 'DECISION',
            'reference' => $reference,
            'document' => DecisionController::LIBELLES[$audience->type_decision] ?? $audience->type_decision,
            'dossier' => $audience->dossier->numero,
            'procedure' => $audience->dossier->type,
            'tribunal' => $audience->dossier->tribunal?->nom,
            'date' => $audience->date_heure->toIso8601String(),
            'empreinte' => $empreinte,
            // null : aucune empreinte à comparer (référence saisie à la main).
            'empreinte_conforme' => $empreinteLue === null ? null : hash_equals($empreinte, strtoupper($empreinteLue)),
        ]);
    }

    private function casier(string $reference)
    {
        $casier = CasierJudiciaire::with('utilisateur')->where('qr_code', $reference)->first();

        if (! $casier) {
            return $this->inconnu($reference);
        }

        return response()->json([
            'authentique' => true,
            'type' => 'CASIER',
            'reference' => $reference,
            'document' => 'Extrait de casier judiciaire (prototype, résultat simulé)',
            'titulaire' => self::masquer($casier->utilisateur?->nom),
            'resultat' => $casier->resultat === 'VIERGE' ? 'Néant' : 'Mentions inscrites',
            'date' => $casier->date_demandee->toIso8601String(),
        ]);
    }

    private function inconnu(string $reference)
    {
        return response()->json([
            'authentique' => false,
            'reference' => $reference,
            'message' => "Aucun document délivré par Audience+ ne correspond à cette référence.",
        ], 404);
    }

    // « Adama Ngom » devient « A. N*** » : assez pour rapprocher le document
    // de la personne qui le présente, sans exposer son identité en ligne.
    private static function masquer(?string $nom): ?string
    {
        if (! $nom) {
            return null;
        }

        $mots = preg_split('/\s+/', trim($nom));
        $prenom = mb_substr($mots[0], 0, 1).'.';
        $famille = count($mots) > 1 ? mb_substr(end($mots), 0, 1).'***' : '';

        return trim("{$prenom} {$famille}");
    }
}
