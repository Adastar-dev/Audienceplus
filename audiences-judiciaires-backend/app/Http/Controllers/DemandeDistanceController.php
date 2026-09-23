<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\DemandeDistance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DemandeDistanceController extends Controller
{
    public function index(Request $request)
    {
        $query = DemandeDistance::with(['audience.dossier', 'utilisateur', 'greffierQuiADonneAvis']);

        if (! in_array($request->user()->role, ['GREFFIER', 'JUGE'], true)) {
            $query->where('id_utilisateur', $request->user()->id_utilisateur);
        }

        return response()->json($query->latest('date_demande')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_audience' => 'required|exists:audiences,id_audience',
            'motif' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $audience = Audience::findOrFail($request->id_audience);
        if ($audience->mode === 'EN_LIGNE') {
            return response()->json(['message' => 'Cette audience est déjà prévue en ligne.'], 422);
        }
        if ($audience->statut !== 'PROGRAMMEE') {
            return response()->json(['message' => "Cette audience n'accepte plus de demande de comparution à distance."], 422);
        }

        $demande = DemandeDistance::create([
            'id_audience' => $request->id_audience,
            'id_utilisateur' => $request->user()->id_utilisateur,
            'motif' => $request->motif,
            'statut' => 'EN_ATTENTE',
            'date_demande' => now(),
        ]);

        return response()->json($demande, 201);
    }

    // Le greffier ne tranche plus lui-même : il donne un avis, favorable ou
    // défavorable, que le juge devra valider pour que la demande soit
    // réellement approuvée ou refusée.
    public function donnerAvis(Request $request, DemandeDistance $demande)
    {
        $validator = Validator::make($request->all(), [
            'avis' => 'required|in:FAVORABLE,DEFAVORABLE',
            'commentaire' => 'required_if:avis,DEFAVORABLE|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($demande->statut !== 'EN_ATTENTE') {
            return response()->json(['message' => 'Cette demande a déjà reçu un avis du greffe.'], 422);
        }

        $demande->update([
            'statut' => $request->avis === 'FAVORABLE' ? 'AVIS_GREFFIER_FAVORABLE' : 'AVIS_GREFFIER_DEFAVORABLE',
            'avis_greffier' => $request->avis,
            'reponse_greffier' => $request->commentaire,
            'avis_greffier_par' => $request->user()->id_utilisateur,
            'avis_greffier_date' => now(),
        ]);

        return response()->json($demande->load('greffierQuiADonneAvis'));
    }

    // Décision finale : réservée au juge, et seulement une fois que le
    // greffier a donné son avis (favorable ou défavorable).
    public function approuver(Request $request, DemandeDistance $demande)
    {
        if (! in_array($demande->statut, ['AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE'], true)) {
            return response()->json([
                'message' => "L'avis du greffier est requis avant toute validation par le juge.",
            ], 422);
        }

        $demande->update([
            'statut' => 'APPROUVEE',
            'date_traitement' => now(),
            'decide_par' => $request->user()->id_utilisateur,
        ]);

        return response()->json($demande);
    }

    public function refuser(Request $request, DemandeDistance $demande)
    {
        $validator = Validator::make($request->all(), [
            'commentaire' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! in_array($demande->statut, ['AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE'], true)) {
            return response()->json([
                'message' => "L'avis du greffier est requis avant toute validation par le juge.",
            ], 422);
        }

        $demande->update([
            'statut' => 'REFUSEE',
            'commentaire_juge' => $request->commentaire,
            'date_traitement' => now(),
            'decide_par' => $request->user()->id_utilisateur,
        ]);

        return response()->json($demande);
    }
}