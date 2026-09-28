<?php

namespace App\Services;

use App\Models\Utilisateur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Extrait le numéro visible sur une photo de CNI avec un modèle de vision,
// via le fournisseur d'IA configuré (Groq par défaut, voir
// config/services.php). Comme les autres services externes du projet, un
// échec est journalisé et renvoie null plutôt que de bloquer le flux appelant.
class CniOcrService
{
    // Largeur maximale envoyée : suffisante pour lire un numéro, et garde
    // l'image sous la limite des fournisseurs (environ 4 Mo en base64 chez Groq).
    private const LARGEUR_MAX = 1600;

    public const REGLES_PHOTO = ['image', 'mimes:jpg,jpeg,png', 'max:5120'];

    // Enregistre la photo de CNI d'un compte (hors racine web, nom aléatoire)
    // et compare le numéro lu au numéro déclaré. Renvoie false si le fichier
    // n'est pas réellement une image.
    // Recto et verso de la carte. Le numéro est lu sur le recto ; s'il n'y
    // est pas trouvé (photo floue, reflet), on tente le verso.
    public function enregistrerPhotosCompte(Utilisateur $utilisateur, UploadedFile $recto, UploadedFile $verso): bool
    {
        foreach ([$recto, $verso] as $photo) {
            if (! str_starts_with((string) $photo->getMimeType(), 'image/')) {
                return false;
            }
        }

        $anciens = array_filter([$utilisateur->cni_photo_path, $utilisateur->cni_verso_path]);
        $cheminRecto = $recto->store('cni-comptes', 'local');
        $cheminVerso = $verso->store('cni-comptes', 'local');

        $numeroDetecte = $this->extraireNumero(Storage::disk('local')->path($cheminRecto))
            ?? $this->extraireNumero(Storage::disk('local')->path($cheminVerso));

        $utilisateur->update([
            'cni_photo_path' => $cheminRecto,
            'cni_verso_path' => $cheminVerso,
            'numero_cni_detecte' => $numeroDetecte,
            'numero_cni_concorde' => ($numeroDetecte !== null && $utilisateur->cni)
                ? $numeroDetecte === $utilisateur->cni
                : null,
        ]);

        Storage::disk('local')->delete(array_diff($anciens, [$cheminRecto, $cheminVerso]));

        return true;
    }

    public function extraireNumero(string $cheminPhotoCni): ?string
    {
        $cle = config('services.ia.api_key');

        if (! $cle) {
            Log::warning('CniOcrService: IA_API_KEY absente, extraction ignorée.');

            return null;
        }

        if (! is_file($cheminPhotoCni)) {
            Log::warning("CniOcrService: fichier introuvable ({$cheminPhotoCni}).");

            return null;
        }

        try {
            [$mime, $contenu] = $this->imagePourEnvoi($cheminPhotoCni);

            $reponse = Http::withToken($cle)
                ->timeout(30)
                ->post(config('services.ia.base_url').'/chat/completions', [
                    'model' => config('services.ia.modele_vision'),
                    'max_tokens' => 30,
                    'temperature' => 0,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "Extrait uniquement le numéro d'identification (CNI) visible sur "
                                    ."cette image, sans aucun autre texte ni ponctuation. Si aucun numéro "
                                    ."n'est lisible, réponds exactement AUCUN.",
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => "data:{$mime};base64,".base64_encode($contenu)],
                            ],
                        ],
                    ]],
                ]);

            if (! $reponse->successful()) {
                Log::warning('CniOcrService: échec API - '.$reponse->status().' '.mb_substr($reponse->body(), 0, 500));

                return null;
            }

            $texte = trim((string) $reponse->json('choices.0.message.content'));
            $chiffres = preg_replace('/\D/', '', $texte);

            return $chiffres !== '' ? $chiffres : null;
        } catch (\Throwable $e) {
            Log::warning('CniOcrService: exception - '.$e->getMessage());

            return null;
        }
    }

    // Réduit la photo si elle est trop large (photos de téléphone). Sans
    // l'extension GD, l'image est envoyée telle quelle.
    private function imagePourEnvoi(string $chemin): array
    {
        $mime = mime_content_type($chemin) ?: 'image/jpeg';
        $contenu = file_get_contents($chemin);

        if (! function_exists('imagecreatefromstring') || ! ($image = @imagecreatefromstring($contenu))) {
            return [$mime, $contenu];
        }

        $largeur = imagesx($image);
        if ($largeur > self::LARGEUR_MAX) {
            $image = imagescale($image, self::LARGEUR_MAX);
        }

        // JPEG si GD le gère (plus léger), sinon PNG.
        ob_start();
        if (function_exists('imagejpeg')) {
            imagejpeg($image, null, 85);
            $mimeSortie = 'image/jpeg';
        } else {
            imagepng($image, null, 9);
            $mimeSortie = 'image/png';
        }
        $sortie = ob_get_clean();
        imagedestroy($image);

        return [$mimeSortie, $sortie];
    }
}
