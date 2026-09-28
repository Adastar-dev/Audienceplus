<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\DemandeDistance;
use App\Models\Dossier;
use App\Models\ProcesVerbal;

// Statistiques d'activité de la juridiction pour l'administrateur : dossiers
// par type de procédure, issue des audiences et délai moyen de jugement.
class StatistiqueController extends Controller
{
    public function index()
    {
        $dossiers = Dossier::with(['audiences' => fn ($q) => $q->where('type_decision', 'JUGEMENT')])->get();
        $audiences = Audience::all(['id_audience', 'statut', 'type_decision']);

        $parType = $dossiers->groupBy('type')->map(function ($groupe, $type) {
            // Délai entre l'enregistrement du dossier et l'audience où le
            // jugement a été rendu.
            $delais = $groupe
                ->map(function (Dossier $dossier) {
                    $jugement = $dossier->audiences->sortBy('date_heure')->first();

                    return $jugement && $dossier->date_creation
                        ? $dossier->date_creation->diffInDays($jugement->date_heure)
                        : null;
                })
                ->filter(fn ($delai) => $delai !== null);

            return [
                'type' => $type,
                'total' => $groupe->count(),
                'en_cours' => $groupe->whereIn('statut', ['EN_COURS', 'RENVOYE'])->count(),
                'juges' => $groupe->whereIn('statut', ['JUGE', 'CLOTURE'])->count(),
                'archives' => $groupe->where('statut', 'ARCHIVE')->count(),
                'delai_moyen_jours' => $delais->isEmpty() ? null : round($delais->avg(), 1),
            ];
        })->sortByDesc('total')->values();

        $decidees = $audiences->whereNotNull('type_decision');

        return response()->json([
            'totaux' => [
                'dossiers' => $dossiers->count(),
                'audiences' => $audiences->count(),
                'jugements' => $decidees->where('type_decision', 'JUGEMENT')->count(),
                'archives' => $dossiers->where('statut', 'ARCHIVE')->count(),
                'comparutions_distance' => DemandeDistance::where('statut', 'APPROUVEE')->count(),
                'pv_contestes' => ProcesVerbal::whereNotNull('contestation_avocat')->count(),
            ],
            'taux_renvoi' => $decidees->isEmpty()
                ? null
                : round(100 * $decidees->where('type_decision', 'RENVOI')->count() / $decidees->count(), 1),
            'audiences_par_statut' => $audiences->countBy('statut'),
            'par_type' => $parType,
        ]);
    }
}
