# Documentation Backend — Audience+

## Organisation générale

Le code du backend suit l'organisation conventionnelle d'un projet Laravel.

```
app/
  Http/Controllers/   Contrôleurs REST, organisés par domaine fonctionnel
  Models/              Modèles Eloquent
  Services/            Intégration des services externes
database/
  migrations/          Schéma de base de données versionné
routes/
  api.php              Définition des routes de l'API
```

- **Contrôleurs** (`app/Http/Controllers`) : organisés par domaine fonctionnel (authentification, utilisateurs, dossiers, audiences, pièces, notifications, etc.), chacun exposant un sous-ensemble cohérent de l'API.
- **Modèles Eloquent** (`app/Models`) : représentent les entités du domaine — Utilisateur, Dossier, Audience, Pièce, ProcèsVerbal, Signature, etc.
- **Services** (`app/Services`) : isolent l'intégration des services externes (transcription audio, vérification OTP, lecture OCR, envoi de notifications), injectés dans les contrôleurs qui en ont besoin plutôt qu'appelés directement.
- **Middleware** : le contrôle d'accès par rôle est centralisé dans un middleware unique, `EnsureRole`, appliqué aux routes de l'API.
- **Migrations** (`database/migrations`) : gèrent l'évolution versionnée du schéma de base de données.

## Contrôleurs de l'API

| Contrôleur | Rôle |
|---|---|
| `AuthController` | Inscription, connexion, déconnexion, blocage temporaire après échecs répétés |
| `UtilisateurController` | Gestion des comptes (création, modification, suppression), listes filtrées par rôle, vérification d'identité |
| `DossierController` | Création d'un dossier et de ses parties liées, consultation filtrée selon le rôle (scope `visiblesPar`), mise à jour du statut, enregistrement de l'avis du procureur |
| `AudienceController` | Programmation, consultation, ouverture/fermeture de l'audience, attribution d'une salle virtuelle, enregistrement de la décision (jugement, renvoi, délibéré), convocation automatique des parties liées au dossier |
| `ConvocationController` | Consultation des convocations, confirmation, demande et traitement des reports, relance |
| `DemandeDistanceController` | Dépôt d'une demande de comparution à distance par un justiciable ou un avocat, approbation ou refus par le greffier |
| `ProcesVerbalController` | Rédaction du procès-verbal par le greffier, transcription automatique, validation ou rejet par le juge |
| `SignatureController` | Signature électronique du procès-verbal validé |
| `PieceController` | Dépôt et consultation des pièces jointes à un dossier |
| `ParticipationController` | Gestion de la présence des participants à une audience à distance (admission, refus, micro, caméra) |
| `NotificationController` | Consultation des notifications et accusé de réception |
| `CasierJudiciaireController` | Demande et suivi d'un extrait de casier judiciaire (module prototype) |
| `TribunalController` | Gestion des tribunaux et de leurs salles virtuelles |
| `MessageController` | Messagerie interne entre les rôles amenés à collaborer sur un dossier (greffier ↔ juge, greffier ↔ procureur, greffier ↔ avocat, procureur ↔ juge, avocat ↔ justiciable) |
| `LogActiviteController` | Consultation des journaux d'activité de la plateforme, à des fins de traçabilité et d'audit |

## Modules métier

### Authentification et gestion des comptes
Authentification par Laravel Sanctum (jeton délivré à la connexion, transmis à chaque requête). L'inscription est réservée aux justiciables et aux avocats, et exige un numéro de pièce d'identité (CNI, 10 à 13 chiffres) et, pour un avocat, un numéro de barreau. La connexion accepte email ou téléphone. Protection contre les tentatives répétées : blocage temporaire (15 min) après 5 échecs consécutifs.

Les comptes institutionnels (juge, greffier, procureur, administrateur) sont créés par un administrateur, qui peut aussi créer un compte avocat/justiciable pour un usager sans accès internet (avec mot de passe temporaire généré). Les comptes avocat/justiciable peuvent ensuite être marqués comme vérifiés par un administrateur.

### Gestion des dossiers
Chaque dossier reçoit un numéro unique au format `TRB-DKR-année-séquence` (ex. `TRB-DKR-2026-0001`). Un dossier associe un demandeur et un défendeur (compte justiciable et/ou avocat le représentant). Visibilité restreinte aux parties et aux greffiers/juges/procureurs du tribunal concerné. Le procureur peut enregistrer un avis consultatif horodaté sur les dossiers dont le type le justifie (adoption, rectification d'acte, etc.).

### Programmation et déroulement des audiences
À la programmation, les parties concernées sont automatiquement convoquées (notification SMS ou email). À l'ouverture d'une audience à distance (ou après approbation d'une demande de comparution à distance), une salle virtuelle Jitsi est attribuée automatiquement. À l'issue, le juge enregistre une décision (jugement, renvoi, délibéré), et le statut du dossier ainsi que les parties notifiées sont mis à jour en conséquence.

### Procès-verbal et signature électronique
Rédaction manuelle par le greffier, ou assistée par transcription automatique d'un enregistrement audio via l'API Whisper d'OpenAI (dégradation gracieuse si la transcription échoue). Cycle de validation : greffier → juge (validation = clôture définitive, ou rejet avec commentaire pour correction). La signature électronique repose sur un hachage SHA-256 du contenu, recalculable à tout moment pour vérifier l'intégrité du document.

### Notifications et convocations
Convocations automatiques par SMS (API SMS Sénégal d'Orange) ou email selon les coordonnées disponibles. Confirmation ou demande de report par la partie convoquée (motif obligatoire), approbation/refus par le greffier avec reprogrammation, relance possible par le greffier.

## Environnement et outils

- **Langage/Framework** : PHP + Laravel (API RESTful)
- **Base de données** : MySQL, via l'ORM Eloquent
- **Authentification** : Laravel Sanctum
- **Tests** : Pest/PHPUnit — 103 tests automatisés couvrant l'authentification et le contrôle des rôles, le cycle de vie dossier/audience, les demandes de comparution à distance, le dépôt/téléchargement des pièces, l'avis du procureur
- **Environnement local de développement** : XAMPP (Apache, MySQL, PHP)
- **Tests automatisés** : exécutés sur une base SQLite en mémoire, isolée de la base MySQL de développement
- **Sécurité** : protections intégrées Laravel contre CSRF et injections SQL ; validation stricte des données entrantes ; contrôle d'accès aux pièces jointes conditionné à la vérification d'identité ; journalisation des échecs d'intégration avec les services externes (sans exposition d'information sensible côté client)

## Architecture de déploiement cible

En production : Nginx exposant l'API Laravel via PHP-FPM, base de données MySQL dédiée, stockage des pièces jointes, connexions sortantes vers les services externes (Jitsi Meet, API SMS Sénégal d'Orange, OpenAI) derrière HTTPS. Les secrets d'accès (clés API) sont externalisés dans des variables d'environnement non versionnées. Le backend et le frontend sont conteneurisés avec Docker, ce qui homogénéise les environnements et facilite le déploiement d'une juridiction à l'autre.
