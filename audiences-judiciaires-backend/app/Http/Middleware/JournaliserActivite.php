<?php

namespace App\Http\Middleware;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\DemandeDistance;
use App\Models\Dossier;
use App\Models\LogActivite;
use App\Models\ParticipationAudience;
use App\Models\Piece;
use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// Journal d'activité : chaque action réussie qui modifie des données (POST,
// PUT, PATCH, DELETE) est enregistrée avec son auteur, son adresse IP et
// l'élément concerné. La connexion, l'inscription et les échecs de connexion
// sont journalisés directement dans AuthController (l'utilisateur n'y est pas
// encore authentifié).
class JournaliserActivite
{
    private const LIBELLES = [
        'AudienceController@store' => "Programmation d'une audience",
        'AudienceController@decider' => "Enregistrement de la décision",
        'AudienceController@fermer' => "Clôture de l'audience",
        'AudienceController@ouvrir' => "Ouverture de l'audience",
        'AudienceController@jugeConnecte' => 'Entrée du juge dans la salle virtuelle',
        'AudienceController@jugeDeconnecte' => 'Sortie du juge de la salle virtuelle',
        'AudienceController@admettreParticipant' => 'Admission dans la salle',
        'AudienceController@refuserParticipant' => 'Refus d\'entrée dans la salle',
        'ParticipationController@marquerPresent' => 'Émargement : présent',
        'ParticipationController@marquerAbsent' => 'Émargement : absent',
        'ParticipationController@envoyerOtp' => 'Demande de code de vérification',
        'ParticipationController@verifierOtp' => 'Code de vérification confirmé',
        'ProcesVerbalController@storeOrUpdate' => 'Rédaction du procès-verbal',
        'ProcesVerbalController@transcrire' => "Transcription de l'enregistrement",
        'ProcesVerbalController@transmettre' => 'Transmission du procès-verbal au juge',
        'ProcesVerbalController@valider' => 'Validation du procès-verbal',
        'ProcesVerbalController@rejeter' => 'Rejet du procès-verbal',
        'ProcesVerbalController@donnerAvis' => 'Avis du procureur sur le procès-verbal',
        'ProcesVerbalController@contester' => 'Contestation du procès-verbal',
        'CasierJudiciaireController@store' => "Demande d'extrait de casier judiciaire",
        'ConvocationController@confirmer' => 'Confirmation de présence',
        'ConvocationController@demanderReport' => 'Demande de report',
        'ConvocationController@donnerAvisReport' => 'Avis du greffier sur une demande de report',
        'ConvocationController@approuverReport' => 'Report accordé',
        'ConvocationController@refuserReport' => 'Report refusé',
        'ConvocationController@relancer' => 'Relance de convocation',
        'DemandeDistanceController@store' => 'Demande de comparution à distance',
        'DemandeDistanceController@donnerAvis' => 'Avis du greffier sur une comparution à distance',
        'DemandeDistanceController@approuver' => 'Comparution à distance accordée',
        'DemandeDistanceController@refuser' => 'Comparution à distance refusée',
        'DossierController@store' => "Création d'un dossier",
        'DossierController@update' => 'Modification du dossier',
        'DossierController@archiver' => 'Archivage du dossier',
        'DossierController@donnerAvis' => 'Avis du procureur sur le dossier',
        'PieceController@store' => "Dépôt d'une pièce",
        'PieceController@valider' => "Validation d'une pièce",
        'PieceController@destroy' => "Suppression d'une pièce",
        'AuthController@logout' => 'Déconnexion',
        'MessageController@store' => "Envoi d'un message",
        'IdentiteController@deposerPhotoCni' => 'Dépôt de la photo de la CNI (recto et verso)',
        'CompteController@completer' => 'Complément des informations du compte',
        'NotificationController@accuserReception' => 'Accusé de réception',
        'SignatureController@store' => "Scellement d'un document",
        'TribunalController@store' => "Création d'un tribunal",
        'UtilisateurController@store' => "Création d'un compte",
        'UtilisateurController@update' => "Modification d'un compte",
        'UtilisateurController@destroy' => "Suppression d'un compte",
        'UtilisateurController@verifierIdentite' => "Vérification d'identité d'un compte",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        if ($request->isMethodSafe() || ! $reponse->isSuccessful() || ! $request->user()) {
            return $reponse;
        }

        $action = Str::afterLast((string) $request->route()?->getActionName(), '\\');
        $libelle = self::LIBELLES[$action] ?? null;

        if ($libelle === null) {
            return $reponse;
        }

        $cible = $this->decrireCible($request, $reponse);

        LogActivite::create([
            'id_utilisateur' => $request->user()->id_utilisateur,
            'action' => Str::limit($cible ? "{$libelle} - {$cible}" : $libelle, 250),
            'adresse_ip' => $request->ip(),
        ]);

        return $reponse;
    }

    private function decrireCible(Request $request, Response $reponse): ?string
    {
        $parametres = $request->route()?->parameters() ?? [];
        $morceaux = [];

        foreach ($parametres as $valeur) {
            $morceaux[] = match (true) {
                $valeur instanceof Dossier => "dossier {$valeur->numero}",
                $valeur instanceof Audience => "audience n°{$valeur->id_audience}".($valeur->dossier ? " (dossier {$valeur->dossier->numero})" : ''),
                $valeur instanceof Utilisateur => "compte de {$valeur->nom}",
                $valeur instanceof ParticipationAudience => 'participant : '.($valeur->utilisateur?->nom ?? "n°{$valeur->getKey()}"),
                $valeur instanceof Piece => "pièce n°{$valeur->getKey()}",
                $valeur instanceof Convocation => "convocation n°{$valeur->getKey()}",
                $valeur instanceof DemandeDistance => "demande n°{$valeur->getKey()}",
                default => null,
            };
        }

        // Création : l'élément créé est dans la réponse.
        if (! $parametres && method_exists($reponse, 'getData')) {
            $donnees = (array) $reponse->getData(true);
            $morceaux[] = match (true) {
                isset($donnees['numero']) => "dossier {$donnees['numero']}",
                isset($donnees['qr_code']) => "référence {$donnees['qr_code']}",
                isset($donnees['id_audience'], $donnees['date_heure']) => "audience n°{$donnees['id_audience']}",
                isset($donnees['nom'], $donnees['role']) => "compte de {$donnees['nom']}",
                default => null,
            };
        }

        return implode(', ', array_filter($morceaux)) ?: null;
    }
}
