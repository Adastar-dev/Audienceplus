# Analyse architecturale — Audience+

## Schéma global

L'architecture d'Audience+ s'organise autour d'un accès via navigateur web, qui communique en HTTPS avec un serveur **Nginx** faisant office de proxy inverse. Ce dernier redirige les requêtes vers le backend **Laravel**, qui expose une API REST sécurisée par **Laravel Sanctum**.

Le backend centralise l'ensemble de la logique métier et orchestre les échanges avec :
- la base de données **MySQL** (persistance : dossiers, audiences, utilisateurs) ;
- le système de stockage de fichiers (pièces jointes aux dossiers) ;
- **Jitsi Meet** pour les audiences à distance en visioconférence sécurisée ;
- le module de notifications : SMS via l'**API SMS Sénégal d'Orange** et emails via **SMTP** ;
- l'**API d'OpenAI** (Whisper), pour la transcription automatique des procès-verbaux et la reconnaissance optique de caractères (OCR) sur les pièces d'identité.

La vérification de l'identité du participant repose sur un code de vérification à 6 chiffres envoyé par SMS (OTP), via l'API SMS Sénégal d'Orange. L'indicateur OCR n'est volontairement pas un verrou d'accès : il produit un indicateur de confiance consulté par le greffier ou le juge, sans jamais bloquer automatiquement une connexion.

### Parcours en couches (1 à 5)

1. Le frontend émet une requête HTTPS.
2. Nginx reçoit la requête, point d'entrée du système.
3. Le processus PHP-FPM exécute l'application Laravel.
4. Le middleware Sanctum et le middleware de rôle (`EnsureRole`) vérifient l'authentification et les droits selon le rôle (justiciable, avocat, greffier, procureur, juge, administrateur).
5. La requête accède aux services de données : stockage de fichiers, MySQL, services externes.

## Description des couches

### a. Couche présentation (frontend React)
- SPA React : routage client (React Router), gestion d'état, affichage adapté à chaque rôle.
- Tableaux de bord dédiés aux six profils d'utilisateurs.
- Client HTTP (Axios) avec intercepteurs pour le jeton d'authentification et la gestion des erreurs.
- Interface responsive (menu coulissant sur mobile).

### b. Couche logique métier (backend Laravel)
- Contrôleurs REST organisés par domaine (dossiers, audiences, pièces, utilisateurs, notifications, participations).
- Classes de services dédiées aux intégrations externes : `NotificationDispatcher`, `WhisperTranscriptionService`, `CniOcrService`, `FaceComparisonService`.
- Règles métier propres au contexte judiciaire : contrôle des statuts d'audience, enregistrement de l'avis du procureur, blocage des demandes de comparution à distance sur une audience non programmée.

### c. Couche persistance (MySQL)
- Modèles Eloquent : utilisateurs, dossiers, audiences, pièces, procès-verbaux, notifications.
- Aucun accès direct aux données : tout accès passe par les contrôles d'authentification et d'autorisation de la couche métier.
- Migrations versionnées, garantissant la reproductibilité du schéma entre environnements.

### d. Couche sécurité et authentification (Sanctum)
- Authentification par jeton via Laravel Sanctum.
- Middleware `EnsureRole` restreignant chaque route de l'API aux rôles autorisés.
- Contrôle d'accès aux pièces d'un dossier conditionné à la vérification d'identité, même pour un compte légitimement rattaché.
- Chiffrement des communications en HTTPS.

### e. Couche communication (Jitsi Meet, API SMS Sénégal d'Orange)
- Jitsi Meet : audiences à distance en visioconférence sécurisée (audio, vidéo, WebRTC).
- API SMS Sénégal d'Orange : envoi de SMS (convocations, rappels), complété par l'envoi d'emails via SMTP.

## Connexion à une audience à distance (Jitsi Meet)

1. Le justiciable ou l'avocat formule une demande de connexion.
2. Le juge ouvre l'audience puis entre dans la salle : le backend lui délivre un jeton JWT signé avec le rôle de modérateur. Une fois connecté, il active la salle d'attente et le backend enregistre sa présence.
3. Le backend vérifie que l'utilisateur est bien partie au dossier, que l'audience est en cours et que le juge est présent, puis lui délivre un jeton JWT de simple participant pour cette salle uniquement.
4. Le serveur Jitsi auto-hébergé refuse toute connexion sans jeton valide. Le rôle de modérateur provient uniquement du jeton, et non de l'ordre d'arrivée : seul le juge est hôte de l'audience.

## Choix technologiques

### Laravel (backend)
Robustesse, écosystème riche (Eloquent ORM, Sanctum, migrations, tests intégrés via Pest/PHPUnit), rapidité de développement. Protections intégrées contre les failles courantes (CSRF, injections SQL) — essentielles pour des données judiciaires sensibles. Architecture MVC claire, réponses JSON (Resources) pour alimenter le frontend React.

### React (frontend)
Réactivité, gestion d'état efficace (contextes, hooks), organisation en composants réutilisables, interface responsive adaptée aussi bien au poste de travail du greffier qu'au smartphone d'un justiciable (avec Tailwind CSS).

### Jitsi Meet (visioconférence)
Open source, auto-hébergeable (confidentialité des échanges judiciaires), intégration simple via une URL de salle générée dynamiquement par le backend. Chiffrement des communications, accès sans installation de logiciel tiers — adapté au contexte de connectivité sénégalais.

### MySQL (base de données)
Maturité, large adoption, bonne intégration native avec Eloquent, robustesse transactionnelle nécessaire pour garantir la cohérence des données lors d'opérations sensibles (programmation d'audience, dépôt de pièce).

### Sécurité
Authentification par jeton (Sanctum) et contrôle des rôles, complétés par : chiffrement HTTPS, validation stricte des données entrantes, contrôle d'accès aux pièces jointes conditionné à la vérification d'identité, journalisation des échecs d'intégration avec les services externes (sans exposition d'information sensible côté client).

## Environnement de développement et de test

- Développement : XAMPP (Apache, MySQL, PHP) pour le backend, serveur de développement Vite pour le frontend (rechargement à chaud).
- Tests automatisés : exécutés sur une base de données SQLite en mémoire, isolée de la base MySQL de développement, pour des exécutions rapides et reproductibles.
- Qualité du code : suite de tests automatisés (Pest/PHPUnit, 103 tests) couvrant l'authentification et le contrôle des rôles, le cycle de vie d'un dossier et d'une audience, la gestion des demandes de comparution à distance, le dépôt/téléchargement des pièces, et l'enregistrement de l'avis du procureur — exécutée à chaque évolution significative du code.

## Architecture de production cible

Serveur Nginx exposant l'API Laravel via PHP-FPM, base de données MySQL dédiée, espace de stockage pour les pièces jointes, connexions sortantes vers les services externes (Jitsi Meet, API SMS Sénégal d'Orange, OpenAI) exposées derrière HTTPS. Les secrets d'accès (clés API) sont externalisés dans des variables d'environnement non versionnées, pour limiter les risques de fuite d'information sensible.

Le backend Laravel et le frontend React sont conteneurisés avec Docker et Docker Compose, ce qui garantit un environnement homogène entre développement, tests et production, et facilite le déploiement d'une juridiction à l'autre.
