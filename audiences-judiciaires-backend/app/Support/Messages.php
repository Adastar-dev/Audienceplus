<?php

namespace App\Support;

use App\Models\Audience;
use App\Models\Dossier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

// Textes de toutes les notifications envoyées par Audience+ (email et
// notifications de la plateforme), rassemblés ici pour rester cohérents.
class Messages
{
    private const TYPES_AFFAIRE = [
        'DIVORCE' => 'Divorce et séparation de corps',
        'ADOPTION' => 'Adoption',
        'RECTIFICATION_ACTE' => "Rectification et actes supplétifs d'état civil",
        'CONTENTIEUX_MARIAGE' => 'Contentieux du mariage',
        'FILIATION' => 'Filiation',
        'GARDE_PENSION' => "Garde d'enfants et pension alimentaire",
        'TUTELLE' => 'Tutelle',
        'DECLARATION_ABSENCE_DECES' => "Déclaration d'absence ou de décès présumé",
        'CHANGEMENT_NOM' => 'Changement de nom',
        'EMANCIPATION' => 'Émancipation',
    ];

    // --- Parties (justiciable, avocat) ---

    public static function convocation(Audience $audience): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Convocation',
            "Vous êtes convoqué(e) à une audience du tribunal pour le dossier {$dossier->numero} ({$dossier->parties}).",
            self::detailsAudience($audience),
            "Merci de confirmer votre présence depuis votre espace Audience+. Si vous ne pouvez pas vous déplacer, "
                ."vous pouvez y demander un report ou une comparution à distance, en précisant le motif. "
                ."Le jour de l'audience, munissez-vous de votre pièce d'identité.",
        );
    }

    public static function rappel(Audience $audience): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Rappel',
            "Nous vous rappelons que vous êtes convoqué(e) à l'audience du ".self::date($audience->date_heure)." pour le dossier {$dossier->numero}.",
            self::detailsAudience($audience),
            "Si vous n'avez pas encore confirmé votre présence, faites-le depuis votre espace Audience+. "
                ."En cas d'empêchement, demandez un report ou une comparution à distance sans attendre.",
        );
    }

    // Rappel automatique 48 heures ou 2 heures avant l'audience ; les
    // magistrats (juge, procureur) reçoivent une version sans consignes de
    // comparution.
    public static function rappelAvantAudience(Audience $audience, int $heures, bool $magistrat): Message
    {
        $dossier = $audience->dossier;
        $echeance = $heures >= 24 ? 'dans deux jours' : 'dans deux heures';

        $action = $magistrat
            ? 'Le dossier et les pièces sont consultables dans votre espace Audience+.'
            : "Munissez-vous de votre pièce d'identité. Si vous participez à distance, rendez-vous dans la salle "
                ."d'attente depuis votre espace Audience+ : un code de vérification vous y sera envoyé par email, "
                .'puis le greffier vous fera entrer dans la salle.';

        return new Message(
            'Rappel',
            "Rappel : l'audience du dossier {$dossier->numero} ({$dossier->parties}) a lieu {$echeance}, le ".self::date($audience->date_heure).'.',
            self::detailsAudience($audience),
            $action,
        );
    }

    // Compte sans photo de la carte d'identité (compte créé par
    // l'administrateur, ou verso manquant) : la notification mène à « Mon compte ».
    public static function identiteACompleter(): Message
    {
        return new Message(
            'Identité à compléter',
            "Votre compte Audience+ n'est pas encore complet : déposez une photo du recto et du verso de votre carte nationale d'identité.",
            [],
            "Connectez-vous à Audience+ et ouvrez « Mon compte » pour déposer les deux photos. L'administration vérifiera ensuite "
                .'votre compte ; tant que ce n\'est pas fait, vous n\'avez accès à aucun dossier.',
            lien: '/compte',
        );
    }

    public static function codeVerification(string $code, int $minutes): Message
    {
        return new Message(
            'Code de vérification',
            "Votre code de vérification Audience+ est {$code}. Il est valable {$minutes} minutes.",
            [],
            "Saisissez ce code dans la salle d'attente pour rejoindre l'audience. Ne le communiquez à personne : "
                ."ni le greffe ni le tribunal ne vous le demanderont.",
            // Le code n'apparaît jamais dans les notifications de la plateforme.
            "Un code de vérification vous a été envoyé (valable {$minutes} minutes).",
        );
    }

    public static function decision(Audience $audience, string $typeDecision): Message
    {
        $dossier = $audience->dossier;

        [$texte, $action] = match ($typeDecision) {
            'JUGEMENT' => [
                "Le jugement a été rendu dans le dossier {$dossier->numero} ({$dossier->parties}).",
                'Vous pouvez consulter la décision depuis votre espace Audience+, rubrique « Décisions ».',
            ],
            'RENVOI' => [
                "L'audience du dossier {$dossier->numero} ({$dossier->parties}) a été renvoyée à une date ultérieure.",
                "Une nouvelle convocation vous sera adressée dès qu'une nouvelle date aura été fixée.",
            ],
            default => [
                "Dans le dossier {$dossier->numero} ({$dossier->parties}), l'affaire a été mise en délibéré.",
                "La décision sera rendue ultérieurement ; vous en serez informé(e) par email.",
            ],
        };

        return new Message('Décision', $texte, self::detailsDossier($dossier), $action, idAudience: $audience->id_audience);
    }

    public static function reportAccepte(Audience $audience, CarbonInterface $nouvelleDate): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Report accepté',
            "Votre demande de report pour le dossier {$dossier->numero} a été acceptée. L'audience est reportée au ".self::date($nouvelleDate).'.',
            ['Nouvelle date' => self::date($nouvelleDate)] + self::detailsDossier($dossier),
            'Merci de confirmer votre présence à cette nouvelle date depuis votre espace Audience+.',
        );
    }

    // Report accordé à une autre partie : les autres convoqués, le juge et le
    // procureur sont prévenus de la nouvelle date.
    public static function audienceReportee(Audience $audience, CarbonInterface $nouvelleDate, bool $partie): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Audience reportée',
            "L'audience du dossier {$dossier->numero} ({$dossier->parties}) est reportée au ".self::date($nouvelleDate).'.',
            ['Nouvelle date' => self::date($nouvelleDate)] + self::detailsDossier($dossier),
            $partie
                ? 'Merci de confirmer votre présence à cette nouvelle date depuis votre espace Audience+.'
                : 'Le calendrier des audiences a été mis à jour dans votre espace Audience+.',
        );
    }

    public static function reportRefuse(Audience $audience, string $motif): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Report refusé',
            "Votre demande de report pour le dossier {$dossier->numero} n'a pas été acceptée. L'audience est maintenue le ".self::date($audience->date_heure).'.',
            ['Motif du refus' => $motif] + self::detailsAudience($audience),
            'Votre présence reste requise. Si vous ne pouvez pas vous déplacer, vous pouvez encore demander '
                .'une comparution à distance depuis votre espace Audience+.',
        );
    }

    public static function distanceAcceptee(Audience $audience): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Comparution à distance acceptée',
            "Votre demande de comparution à distance pour le dossier {$dossier->numero} a été acceptée : vous pourrez suivre l'audience par visioconférence.",
            self::detailsAudience($audience),
            "Le jour de l'audience, connectez-vous à Audience+ au plus tôt 30 minutes avant l'heure prévue et ouvrez la salle d'attente. "
                ."Un code de vérification vous sera envoyé par email ; après sa saisie, le greffier ou le juge vous fera entrer dans la salle.",
        );
    }

    public static function distanceRefusee(Audience $audience, string $motif): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Comparution à distance refusée',
            "Votre demande de comparution à distance pour le dossier {$dossier->numero} n'a pas été acceptée.",
            ['Motif du refus' => $motif] + self::detailsAudience($audience),
            'Votre présence au tribunal reste requise. Si vous ne pouvez pas vous déplacer, vous pouvez demander un report '
                .'depuis votre espace Audience+.',
        );
    }

    // --- Juge, procureur, greffier ---

    public static function audienceProgrammee(Audience $audience): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Audience programmée',
            "Une audience a été programmée pour le dossier {$dossier->numero} ({$dossier->parties}).",
            self::detailsAudience($audience),
            "Le dossier et le calendrier des audiences sont consultables dans votre espace Audience+.",
        );
    }

    public static function dossierAssigne(Dossier $dossier): Message
    {
        return new Message(
            'Dossier assigné',
            "Le dossier {$dossier->numero} ({$dossier->parties}) vous a été assigné pour avis.",
            self::detailsDossier($dossier),
            'Vous pouvez consulter le dossier et enregistrer votre avis depuis votre espace Audience+.',
        );
    }

    public static function demandeReport(Audience $audience, string $demandeur, string $motif): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Demande de report',
            "{$demandeur} demande le report de l'audience du dossier {$dossier->numero}.",
            ['Demandeur' => $demandeur, 'Motif invoqué' => $motif] + self::detailsAudience($audience),
            "Donnez votre avis (favorable ou défavorable) depuis l'espace « Convocations » ; le juge statuera ensuite.",
        );
    }

    public static function reportADecider(Audience $audience, string $avis, ?string $dateProposee): Message
    {
        $dossier = $audience->dossier;
        $details = ['Avis du greffe' => $avis === 'FAVORABLE' ? 'favorable' : 'défavorable'];
        if ($dateProposee) {
            $details['Nouvelle date proposée'] = self::date(Carbon::parse($dateProposee));
        }

        return new Message(
            'Report à décider',
            "Le greffe a donné son avis sur une demande de report dans le dossier {$dossier->numero} : votre décision est attendue.",
            $details + self::detailsAudience($audience),
            'Acceptez ou refusez le report depuis votre espace Audience+, rubrique « Validations du greffier ».',
        );
    }

    public static function demandeDistance(Audience $audience, string $demandeur, string $motif): Message
    {
        $dossier = $audience->dossier;

        return new Message(
            'Demande à distance',
            "{$demandeur} demande à comparaître à distance à l'audience du dossier {$dossier->numero}.",
            ['Demandeur' => $demandeur, 'Motif invoqué' => $motif] + self::detailsAudience($audience),
            "Donnez votre avis depuis l'espace « Demandes à distance » ; le juge statuera ensuite.",
        );
    }

    public static function distanceADecider(Audience $audience, string $avis): Message
    {
        $dossier = $audience->dossier;
        $avisLisible = $avis === 'FAVORABLE' ? 'favorable' : 'défavorable';

        return new Message(
            'Demande à distance à décider',
            "Le greffe a donné son avis sur une demande de comparution à distance dans le dossier {$dossier->numero} : votre décision est attendue.",
            ['Avis du greffe' => $avisLisible] + self::detailsAudience($audience),
            'Acceptez ou refusez la demande depuis votre espace Audience+, rubrique « Validations du greffier ».',
        );
    }

    // Décision du juge sur une demande de comparution à distance, pour le greffe
    // (qui prépare la salle virtuelle ou attend la partie au tribunal).
    public static function distanceDecidee(Audience $audience, string $demandeur, bool $acceptee, ?string $motif = null): Message
    {
        $dossier = $audience->dossier;
        $details = ['Demandeur' => $demandeur] + ($motif ? ['Motif du refus' => $motif] : []);

        return new Message(
            $acceptee ? 'Comparution à distance accordée' : 'Comparution à distance refusée',
            $acceptee
                ? "Le juge a accepté que {$demandeur} comparaisse à distance à l'audience du dossier {$dossier->numero}."
                : "Le juge a refusé la demande de comparution à distance de {$demandeur} dans le dossier {$dossier->numero}.",
            $details + self::detailsAudience($audience),
            $acceptee
                ? "Le jour de l'audience, faites entrer cette partie dans la salle virtuelle depuis l'écran d'émargement, une fois son code de vérification confirmé."
                : 'Cette partie devra se présenter au tribunal.',
        );
    }

    // --- Mise en forme ---

    public static function date(CarbonInterface $date): string
    {
        return $date->copy()->locale('fr')->translatedFormat('l j F Y \à H\hi');
    }

    private static function detailsDossier(Dossier $dossier): array
    {
        $dossier->loadMissing('tribunal');

        return array_filter([
            'Dossier' => $dossier->numero,
            'Parties' => $dossier->parties,
            "Nature de l'affaire" => self::TYPES_AFFAIRE[$dossier->type] ?? $dossier->type,
            'Tribunal' => $dossier->tribunal ? trim($dossier->tribunal->nom.' ('.$dossier->tribunal->ville.')') : null,
        ]);
    }

    private static function detailsAudience(Audience $audience): array
    {
        return ['Date et heure' => self::date($audience->date_heure)] + self::detailsDossier($audience->dossier);
    }
}
