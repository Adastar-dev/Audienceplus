# Audience+

Plateforme de gestion d'audiences judiciaires et d'état civil à distance pour le Sénégal, développée dans le cadre d'un mémoire de fin d'études (ESMT DAR26/LPTI).

Audience+ permet la programmation, la tenue et le suivi d'audiences (à distance ou en présentiel), la gestion des dossiers judiciaires, la rédaction et la signature électronique des procès-verbaux, ainsi que la communication (SMS, email, messagerie interne) entre les différents acteurs de la chaîne judiciaire.

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Rôles utilisateurs](#rôles-utilisateurs)
- [Stack technique](#stack-technique)
- [Architecture](#architecture)
- [Structure du dépôt](#structure-du-dépôt)
- [Installation](#installation)
- [Tests](#tests)
- [Documentation](#documentation)

## Fonctionnalités

- **Authentification** par jeton (Laravel Sanctum), inscription encadrée (CNI pour les justiciables, numéro de barreau pour les avocats), blocage temporaire après tentatives répétées.
- **Gestion des dossiers** judiciaires avec numérotation automatique (format `TRB-DKR-année-séquence`) ; un justiciable ou un avocat ne voit que ses propres dossiers, et seulement une fois son compte vérifié.
- **Programmation et déroulement des audiences**, convocations automatiques (SMS/email), reports (avis du greffier, décision du juge), attribution dynamique d'une salle Jitsi pour les audiences à distance.
- **Comparution à distance** : demande motivée du justiciable ou de l'avocat, avis du greffier, décision du juge ; vérification d'identité (OTP + OCR) dans une salle d'attente ouverte 30 minutes avant l'audience.
- **Visioconférence Jitsi auto-hébergée**, accès par jeton JWT signé ; seul le juge est modérateur et personne n'entre avant lui.
- **Procès-verbaux** avec transcription audio automatique (API Whisper d'OpenAI), cycle de validation greffier → juge, avis du procureur, contestation par l'avocat, et scellement d'intégrité (empreinte SHA-256 vérifiable).
- **Messagerie interne** entre les rôles amenés à collaborer sur un dossier (greffier ↔ juge, greffier ↔ procureur, greffier ↔ avocat, procureur ↔ juge, avocat ↔ justiciable).
- **Notifications** par SMS (API SMS Sénégal d'Orange) et par email (SMTP).
- **Vérification d'identité** par OTP (SMS) et reconnaissance optique de caractères (OCR) sur les pièces d'identité, à titre indicatif (non bloquant).
- **Journalisation** des activités de la plateforme à des fins de traçabilité et d'audit.

## Rôles utilisateurs

| Rôle | Description |
|---|---|
| Justiciable | Partie à un dossier, peut demander une comparution à distance, consulter ses décisions, échanger via la messagerie |
| Avocat | Représente un ou plusieurs justiciables, suit ses dossiers et convocations |
| Greffier | Crée et gère les dossiers, programme les audiences, rédige les procès-verbaux — rôle disposant de l'espace fonctionnel le plus étendu |
| Juge | Ouvre et ferme l'audience (seul modérateur Jitsi), enregistre les décisions (jugement, renvoi, délibéré), valide les procès-verbaux, tranche les demandes de report et de comparution à distance après l'avis du greffier |
| Procureur | Enregistre un avis consultatif sur les dossiers qui le justifient (ex. adoption, rectification d'acte) |
| Administrateur | Gère les comptes utilisateurs, les tribunaux, les journaux et les paramètres de sécurité |

## Stack technique

**Backend**
- PHP / Laravel — API RESTful
- Laravel Sanctum — authentification par jeton
- MySQL — persistance des données, via l'ORM Eloquent
- Pest/PHPUnit — suite de tests automatisés (122 tests)

**Frontend**
- JavaScript (React), sans TypeScript
- Vite — bundling et rechargement à chaud
- Tailwind CSS — mise en forme, sans bibliothèque de composants tierce

**Services externes**
- Jitsi Meet — visioconférence des audiences à distance, auto-hébergée dans Docker avec authentification JWT
- API SMS Sénégal d'Orange — envoi de SMS (convocations, décisions, OTP)
- API OpenAI — transcription audio des procès-verbaux (Whisper) et lecture OCR du numéro des pièces d'identité
- SMTP — envoi d'emails

**Outils de développement**
- Visual Studio Code
- XAMPP (environnement local Apache/MySQL/PHP)
- phpMyAdmin
- Git / GitHub
- Docker et Docker Compose (conteneurisation déjà configurée)

## Architecture

L'application s'organise autour d'un accès navigateur en HTTPS, relayé par un proxy inverse Nginx vers le backend Laravel (API REST sécurisée par Sanctum). Le backend centralise la logique métier et orchestre les échanges avec MySQL, le stockage de fichiers, Jitsi Meet, l'API SMS Sénégal d'Orange et l'API OpenAI.

Couches principales :
1. **Présentation** (frontend React) — SPA avec routage client, tableaux de bord par rôle, client HTTP Axios
2. **Logique métier** (backend Laravel) — contrôleurs REST par domaine, services dédiés aux intégrations externes
3. **Persistance** (MySQL) — modèles Eloquent, migrations versionnées
4. **Sécurité et authentification** (Sanctum) — jeton d'accès, middleware de contrôle des rôles (`EnsureRole`)
5. **Communication** (Jitsi Meet, API SMS Sénégal d'Orange) — visioconférence et notifications

Voir [DOCUMENTATION_ARCHITECTURE.md](./DOCUMENTATION_ARCHITECTURE.md) pour le détail.

## Structure du dépôt

```
audiences-judiciaires-backend/    API Laravel
audiences-judiciaires-frontend/   Application React
docker-compose.yml                Plateforme complète (API, interface, MySQL, Jitsi)
```

## Installation

### Avec Docker (recommandé)

```bash
docker compose up -d --build
docker compose exec app php artisan db:seed   # comptes de démonstration, une seule fois sur une base vide
```

Au premier démarrage, le conteneur `app` installe les dépendances, crée le `.env`, génère la clé et lance les migrations.

| Service | Adresse |
|---|---|
| Interface React | http://localhost:5173 |
| API Laravel | http://localhost:8000/api |
| Adminer (base de données) | http://localhost:8080 |
| Jitsi | https://localhost:8443 (certificat auto-signé à accepter une fois) |

Comptes de démonstration (mot de passe `password`) : `f.diallo@justice.sn` (juge), `m.sy@justice.sn` (greffier), `c.ba@justice.sn` (procureur), `awa.fall@barreau.sn` (avocat), `aida.ndiaye@mail.sn` (justiciable), `admin@justice.sn` (administrateur).

Les secrets par défaut de `docker-compose.yml` (`secret`, `dev-secret-a-changer-en-production`) sont réservés au développement.

### Sans Docker

Backend :
```bash
cd audiences-judiciaires-backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Frontend :
```bash
cd audiences-judiciaires-frontend
npm install
npm run dev
```

## Tests

```bash
cd audiences-judiciaires-backend
php artisan test
```

Les tests tournent sur une base SQLite en mémoire. **Ne les lancez pas dans le conteneur Docker** : son environnement impose MySQL. `phpunit.xml` force désormais SQLite et `tests/TestCase.php` refuse de démarrer sur une autre base, mais la règle reste de lancer les tests en local.

La suite couvre l'authentification et le contrôle des rôles, le cycle de vie d'un dossier et d'une audience, les demandes de comparution à distance, la présence du juge avant l'accès à la salle Jitsi, le dépôt/téléchargement des pièces, l'avis du procureur et l'intégrité des signatures. Les tests d'OCR et de signature avec image nécessitent l'extension PHP GD.

## Documentation

- [DOCUMENTATION_BACKEND.md](./DOCUMENTATION_BACKEND.md) — organisation et détail du backend Laravel
- [DOCUMENTATION_FRONTEND.md](./DOCUMENTATION_FRONTEND.md) — organisation et détail du frontend React
- [DOCUMENTATION_ARCHITECTURE.md](./DOCUMENTATION_ARCHITECTURE.md) — architecture globale et choix techniques

---

Projet réalisé et soutenu par Adama NGOM — DAR26/LPTI, ESMT.
