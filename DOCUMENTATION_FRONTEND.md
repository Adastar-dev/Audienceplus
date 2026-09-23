# Documentation Frontend — Audience+

## Organisation générale

Le frontend est une application React (JavaScript, sans TypeScript), organisée en répertoires reflétant les six profils d'utilisateurs de la plateforme.

```
src/
  pages/          Un sous-dossier par rôle, + auth/ et shared/
  components/     Composants réutilisables (mise en page, UI, intégration Jitsi)
  services/api/   Appels à l'API, un module par ressource
  routes/         Routes protégées par rôle
  constants/
    enums.js      Source unique de vérité (rôles, statuts, types d'audience)
```

- **`src/pages/`** : un sous-dossier par rôle (`admin`, `juge`, `greffier`, `procureur`, `avocat`, `justiciable`), un dossier `auth/` pour les écrans communs de connexion/inscription, et un dossier `shared/` pour les écrans partagés entre rôles (ex. salle d'audience à distance).
- **`src/components/`** : composants réutilisables (mise en page, éléments d'interface, intégration Jitsi).
- **Authentification** : gérée par un contexte React (`AuthContext`).
- **`src/services/api/`** : centralise les appels HTTP, un module par ressource (utilisateurs, audiences, dossiers, etc.), pour éviter de dupliquer la logique d'appel dans les composants.
- **`src/routes/`** : routes protégées par rôle.
- **`src/constants/enums.js`** : centralise l'ensemble des valeurs métier (rôles, statuts de dossier, statuts d'audience, types d'audience), source unique de vérité partagée par tous les écrans.

## Écrans par rôle

### `greffier/` (11 pages — rôle disposant de l'espace fonctionnel le plus étendu)
Dashboard, DossiersList, NouveauDossier, DossierDetail, NouvelleAudience, Convocations, DemandesDistance, DepotPieces, RedactionPV, Emargement, Messagerie

### `juge/` (8 pages)
Dashboard, CalendrierAudiences, DossiersProgrammes, AudienceDetail, SalleAudience, ValidationPV, ValidationsGreffier, Messagerie

### `avocat/` (6 pages)
Dashboard, Dossiers, DossierDetail, Convocations, HistoriqueAudiences, Messagerie

### `justiciable/` (8 pages)
Dashboard, MonDossier, DemandeDistance, SalleAttente, Decisions, CasierJudiciaire, Notifications, Messagerie

### `procureur/` (5 pages)
Dashboard, Dossiers, Decisions, Notifications, Messagerie

### `admin/` (5 pages)
Dashboard, Utilisateurs, Tribunaux, Logs, ParametresSecurite

### `auth/` et `shared/`
Écrans communs de connexion et d'inscription ; écran partagé de salle d'audience à distance (`RejoindreAudience`), réutilisé par les rôles amenés à y participer.

## Intégration Jitsi Meet

Un composant dédié permet de rejoindre une audience à distance sans quitter la plateforme. Avant d'afficher la visioconférence, la page demande au backend un jeton JWT (`GET /audiences/{id}/jitsi-jeton`) qui fixe la salle et le rôle de l'utilisateur : modérateur pour le juge, participant pour les autres. Les participants patientent sur un écran d'attente tant que le juge n'est pas entré dans la salle. L'utilisateur final accède à l'audience depuis un simple navigateur (ordinateur ou smartphone), sans installation d'un logiciel tiers.

## Stack technique

- **React** (JavaScript, sans TypeScript) — SPA, routage client (React Router)
- **Vite** — bundling et rechargement à chaud en développement
- **Tailwind CSS** — mise en forme, sans bibliothèque de composants tierce (pas de shadcn/ui)
- **Axios** — client HTTP, avec intercepteurs pour la gestion automatique du jeton d'authentification et des erreurs
- **Interface responsive** — menu coulissant sur mobile, consultable sur ordinateur comme sur smartphone

## Installation

```bash
cd frontend
npm install
npm run dev
```
