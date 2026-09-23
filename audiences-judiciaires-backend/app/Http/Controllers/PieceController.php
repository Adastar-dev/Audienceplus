<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Piece;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PieceController extends Controller
{
    private const TYPES_AUTORISES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function index(Request $request, Dossier $dossier)
    {
        if (! $dossier->estAccessiblePar($request->user())) {
            abort(403, "Vous n'avez pas accès à ce dossier.");
        }

        return response()->json($dossier->pieces()->with('deposant')->get());
    }

    public function store(Request $request, Dossier $dossier)
    {
        if (! $dossier->estAccessiblePar($request->user())) {
            abort(403, "Vous n'avez pas accès à ce dossier.");
        }

        $validator = Validator::make($request->all(), [
            'fichier' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fichier = $request->file('fichier');

        if (! in_array($fichier->getMimeType(), self::TYPES_AUTORISES, true)) {
            return response()->json(['errors' => ['fichier' => ['Type de fichier non autorisé.']]], 422);
        }
        $chemin = $fichier->store("dossiers/{$dossier->id_dossier}/pieces", 'local');

        $piece = Piece::create([
            'id_dossier' => $dossier->id_dossier,
            'depose_par' => $request->user()->id_utilisateur,
            'nom' => $fichier->getClientOriginalName(),
            'type_fichier' => $fichier->getClientOriginalExtension(),
            'chemin' => $chemin,
            'valide' => false,
            'date_depot' => now(),
        ]);

        return response()->json($piece, 201);
    }

    public function valider(Piece $piece)
    {
        $piece->update(['valide' => true]);

        return response()->json($piece);
    }

    public function destroy(Request $request, Piece $piece)
    {
        if ($piece->valide) {
            return response()->json(['message' => 'Une pièce validée ne peut pas être supprimée.'], 403);
        }

        $utilisateur = $request->user();
        if ($utilisateur->role !== 'GREFFIER' && $piece->depose_par !== $utilisateur->id_utilisateur) {
            return response()->json(['message' => 'Vous ne pouvez supprimer que vos propres pièces.'], 403);
        }

        $piece->delete();

        return response()->json(null, 204);
    }

    // Sert le fichier réel d'une pièce (index() ne renvoie que ses métadonnées).
    // Même contrôle d'accès que index() : il faut avoir accès au dossier.
    public function telecharger(Request $request, Piece $piece)
    {
        if (! $piece->dossier->estAccessiblePar($request->user())) {
            abort(403, "Vous n'avez pas accès à ce dossier.");
        }

        if (! Storage::disk('local')->exists($piece->chemin)) {
            abort(404);
        }

        return Storage::disk('local')->response($piece->chemin, $piece->nom);
    }
}