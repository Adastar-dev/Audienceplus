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
- **Gestion des dossiers** judiciaires avec numérotation automatique (format `TRB-DKR-année-séquence`), visibilité restreinte aux parties et aux rôles institutionnels concernés.
- **Programmation et déroulement des audiences**, convocations automatiques (SMS/email), attribution dynamique d'une salle Jitsi pour les audiences à distance.
- **Comparution à distance** : dépôt, approbation/refus des demandes par le greffier.
- **Rédaction et signature électronique des procès-verbaux**, avec transcription audio automatique (API Whisper d'OpenAI) et cycle de validation greffier → juge.
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
| Juge | Valide les procès-verbaux, enregistre les décisions (jugement, renvoi, délibéré), consulte les validations du greffier |
| Procureur | Enregistre un avis consultatif sur les dossiers qui le justifient (ex. adoption, rectification d'acte) |
| Administrateur | Gère les comptes utilisateurs, les tribunaux, les journaux et les paramètres de sécurité |

## Stack technique

**Backend**
- PHP / Laravel — API RESTful
- Laravel Sanctum — authentification par jeton
- MySQL — persistance des données, via l'ORM Eloquent
- Pest/PHPUnit — suite de tests automatisés (103 tests)

**Frontend**
- JavaScript (React), sans TypeScript
- Vite — bundling et rechargement à chaud
- Tailwind CSS — mise en forme, sans bibliothèque de composants tierce

**Services externes**
- Jitsi Meet — visioconférence sécurisée pour les audiences à distance (auto-hébergeable, open source)
- API SMS Sénégal d'Orange — envoi de SMS (convocations, rappels, OTP)
- API Whisper (OpenAI) — transcription automatique des procès-verbaux et OCR sur les pièces d'identité
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
Backend/    API Laravel
frontend/   Application React
```

## Installation

Backend :
```bash
cd Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Frontend :
```bash
cd frontend
npm install
npm run dev
```

## Tests

```bash
cd Backend
php artisan test
```

Suite de tests Pest/PHPUnit couvrant l'authentification et le contrôle des rôles, le cycle de vie d'un dossier et d'une audience, la gestion des demandes de comparution à distance, le dépôt/téléchargement des pièces, et l'enregistrement de l'avis du procureur.

## Documentation

- [DOCUMENTATION_BACKEND.md](./DOCUMENTATION_BACKEND.md) — organisation et détail du backend Laravel
- [DOCUMENTATION_FRONTEND.md](./DOCUMENTATION_FRONTEND.md) — organisation et détail du frontend React
- [DOCUMENTATION_ARCHITECTURE.md](./DOCUMENTATION_ARCHITECTURE.md) — architecture globale et choix techniques

---

Projet réalisé et soutenu par Adama NGOM — DAR26/LPTI, ESMT.
