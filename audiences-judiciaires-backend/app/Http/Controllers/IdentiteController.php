<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use App\Services\CniOcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// Photos de la CNI (recto et verso) rattachées au compte : déposée à l'inscription, ou à la
// première connexion pour un compte créé par l'administrateur, puis consultée
// par l'administrateur avant de vérifier le compte.
class IdentiteController extends Controller
{
    public function __construct(private CniOcrService $cniOcr)
    {
    }

    public function deposerPhotoCni(Request $request)
    {
        $utilisateur = $request->user();

        if ($utilisateur->identite_verifiee) {
            return response()->json(['message' => 'Votre compte est déjà vérifié : la photo ne peut plus être modifiée.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'cni_photo' => ['required', ...CniOcrService::REGLES_PHOTO],
            'cni_verso' => ['required', ...CniOcrService::REGLES_PHOTO],
        ], [
            'cni_photo.required' => 'La photo du recto de la carte d\'identité est obligatoire.',
            'cni_verso.required' => 'La photo du verso de la carte d\'identité est obligatoire.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! $this->cniOcr->enregistrerPhotosCompte($utilisateur, $request->file('cni_photo'), $request->file('cni_verso'))) {
            return response()->json(['errors' => ['cni_photo' => ["Le fichier n'est pas une image valide."]]], 422);
        }

        return response()->json($utilisateur->fresh());
    }

    // Réservé à l'administrateur (voir routes/api.php) ; ?face=verso pour le verso.
    public function photoCni(Request $request, Utilisateur $utilisateur)
    {
        $chemin = $request->query('face') === 'verso' ? $utilisateur->cni_verso_path : $utilisateur->cni_photo_path;

        if (! $chemin || ! Storage::disk('local')->exists($chemin)) {
            abort(404);
        }

        return Storage::disk('local')->response($chemin);
    }
}
