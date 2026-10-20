# 🌊 AquaSecure — Documentation du Projet (5TWIN)

Bienvenue sur **AquaSecure**, plateforme de surveillance et de gestion du réseau d'eau (zones à risque, alertes, incidents citoyens, infrastructures, projets, financements, interventions, maintenances techniques), enrichie par de l'intelligence artificielle, une carte temps réel et un bot Telegram.

Ce document décrit le projet **tel qu'il est implémenté dans ce dépôt** : démarrage, configuration, base de données, commandes, fonctionnalités, IA, routes, tests et workflow Git.

---

## 📑 Sommaire

1. [Description & Stack Technique](#1-description--stack-technique)
2. [Démarrage Rapide](#2-démarrage-rapide)
3. [Variables d'Environnement](#3-variables-denvironnement)
4. [Base de Données : Entités, Migrations, Seeders, Rôles](#4-base-de-données--entités-migrations-seeders-rôles)
5. [Commandes Artisan & Guide Complet](#5-commandes-artisan--guide-complet)
6. [Fonctionnalités par Module](#6-fonctionnalités-par-module)
7. [Intelligence Artificielle](#7-intelligence-artificielle)
8. [Routes & Contrôle d'Accès](#8-routes--contrôle-daccès)
9. [Tests](#9-tests)
10. [Workflow Git](#10-workflow-git)
11. [Dépannage (Troubleshooting)](#11-dépannage-troubleshooting)
12. [Structure du Projet](#12-structure-du-projet)
13. [Crédits](#13-crédits)

---

## 1. Description & Stack Technique

AquaSecure réunit :

- un **site public** (état du réseau, zones, alertes, incidents, infrastructures, projets, financements, suivi de signalements) ;
- un **back-office par rôle** (admin, manager/gestionnaire, technicien) avec CRUD, workflows (alertes, maintenances), tableaux de bord et exports ;
- de l'**IA** : classification de signalements, analyse de risque des zones et des alertes, analyse d'infrastructures, recommandation de techniciens, centre « Maintenance Intelligence » et un assistant conversationnel en lecture seule ;
- des **cartes Leaflet** (front et back-office), des **graphiques Chart.js**, un **bot Telegram** pour signaler/suivre depuis Telegram et des **e-mails transactionnels Brevo** (invitations techniciens, notifications).

| Couche | Technologie |
| :--- | :--- |
| Backend | PHP `^8.2`, Laravel `^12` (Illuminate, Eloquent, Blade) |
| Frontend | Blade + Tailwind CSS `^4`, Vite `^6`, Axios, modules JS ES |
| Visualisation | Chart.js `^4` (graphiques), Leaflet `^1.9` (cartes) |
| Base de données | SQLite par défaut (`DB_CONNECTION=sqlite`), compatible MySQL/PostgreSQL |
| File d'attente / sessions / cache | Base de données (`QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=database`) |
| IA | API compatible OpenAI (Groq par défaut), Ollama local (chatbot), service Flask de classification (signalements) |
| E-mails | Brevo (API transactionnelle + SMTP de repli `services.brevo`) |
| Messagerie | Bot Telegram en **long polling** (aucun webhook public requis) |
| Tests & qualité | PHPUnit `^11.5`, Laravel Pint (fixeur de style), Laravel Sail/Pail disponibles |
| Utilitaires | `concurrently` (dev scripts), `chart.js`, `leaflet` côté JS |

---

## 2. Démarrage Rapide

### Installation (base neuve)

```bash
# 1. Dépendances PHP
composer install

# 2. Fichier d'environnement
#    Linux/macOS/Git Bash :
cp .env.example .env
#    PowerShell (Windows) :
Copy-Item .env.example .env

# 3. Clé secrète de l'application
php artisan key:generate

# 4. Dépendances JS / CSS puis build
npm install
npm run build

# 5. Lien symbolique du stockage (photos zones / signalements)
php artisan storage:link

# 6. Création du schéma + données de démonstration (base neuve uniquement)
php artisan migrate --seed
```

### Lancer l'application

```bash
# Terminal 1 — serveur Laravel (http://127.0.0.1:8000)
php artisan serve

# Terminal 2 — assets en direct (Vite hot-reload)
npm run dev
```

### Options utiles (notamment sous Windows)

```bash
# Port 8000 déjà occupé : changer de port
php artisan serve --port=8080

# Accès depuis d'autres appareils du réseau local
php artisan serve --host=0.0.0.0 --port=8000

# Serveur lent sous Windows : désactiver le rechargement automatique
php artisan serve --no-reload
```

### Processes facultatifs

```bash
# File d'attente : nécessaire pour les analyses IA différées (ai:analyze-* --queue)
php artisan queue:work

# Bot Telegram (long polling, à laisser tourner)
php artisan telegram:bot
```

> **Comptes de démonstration** : `DatabaseSeeder` crée quatre comptes (rôles `admin`, `manager`, `technician`, `citizen`). Les identifiants se trouvent dans `database/seeders/DatabaseSeeder.php` — ils ne sont volontairement pas reproduits ici.

---

## 3. Variables d'Environnement

Référence officielle : **`.env.example`**. Renseignez vos valeurs dans `.env` (jamais commité). Aucune clé n'est codée en dur dans le code.

### Cœur Laravel

| Variable | Défaut | Rôle |
| :--- | :--- | :--- |
| `APP_URL` | `http://localhost` | URL de base de l'application |
| `APP_DEBUG` | `true` (local) | Affichage des erreurs détaillées |
| `DB_CONNECTION` | `sqlite` | Pilote de base de données |
| `SESSION_DRIVER` | `database` | Stockage des sessions |
| `CACHE_STORE` | `database` | Cache applicatif (analyses IA, cartes, live) |
| `QUEUE_CONNECTION` | `database` | File d'attente (jobs d'analyse IA) |
| `MAIL_MAILER` | `log` | Transport d'e-mail de repli (sans Brevo) |

### Analyse IA — zones / alertes / infrastructures / signalements (`config/ai.php`)

| Variable | Défaut | Rôle |
| :--- | :--- | :--- |
| `AI_API_KEY` | *(repli : `GROQ_API_KEY`)* | Clé du fournisseur LLM — placeholder `your_api_key` |
| `AI_MODEL` | `openai/gpt-oss-120b` | Modèle utilisé pour les analyses |
| `AI_BASE_URL` | `https://api.groq.com/openai/v1` | Endpoint compatible OpenAI |
| `AI_TIMEOUT` | `45` | Délai maximal d'attente (s) |
| `AI_CONNECT_TIMEOUT` | `10` | Délai de connexion (s) |
| `AI_TEMPERATURE` | `0.2` | Température des réponses IA |
| `AI_CACHE_TTL` | `600` | Durée du cache des analyses (s) |
| `AI_AUTO_ANALYZE_ON_CREATE` | `true` | Analyse automatique à la création d'une alerte |
| `AI_CLASSIFICATION_URL` | `http://127.0.0.1:5000` | Service Flask de classification des signalements |
| `GROQ_API_KEY` | *(vide)* | Clé Groq (Maintenance Intelligence, repli d'`AI_API_KEY`) |
| `GROQ_MODEL` | `openai/gpt-oss-120b` | Modèle Groq |
| `GROQ_BASE_URL` | `https://api.groq.com/openai/v1` | Endpoint Groq |

### Assistant conversationnel — chatbot (`config/ai.php` → `chat`)

| Variable | Défaut | Rôle |
| :--- | :--- | :--- |
| `CHAT_AI_BASE_URL` | `http://localhost:11434/v1` | Serveur **Ollama local** (API compatible OpenAI) |
| `CHAT_AI_API_KEY` | `ollama` | Clé fictive locale (séparation stricte avec la clé cloud) |
| `CHAT_AI_MODEL` | `llama3.1:8b` | Modèle Ollama |
| `CHAT_AI_TIMEOUT` | `120` | Délai d'attente (s), Ollama pouvant être lent au 1er jeton |
| `CHAT_AI_MAX_HISTORY` | `10` | Messages d'historique conservés en session |

### Telegram (`config/telegram.php`)

| Variable | Défaut | Rôle |
| :--- | :--- | :--- |
| `TELEGRAM_BOT_TOKEN` | *(vide)* | Jeton du bot (@BotFather) — placeholder `your_api_key` |

### E-mails Brevo (`config/services.php` → `brevo`)

| Variable | Défaut | Rôle |
| :--- | :--- | :--- |
| `BREVO_API_KEY` | *(vide)* | Clé API Brevo (invitations & notifications) — placeholder `your_api_key` |
| `BREVO_MAIL_DRIVER` | `MAIL_MAILER` | Transport Brevo (`api` ou `smtp`) |
| `BREVO_SENDER_NAME` | `AquaSecure` | Nom de l'expéditeur |
| `BREVO_SENDER_EMAIL` | *(vide)* | Adresse d'expédition |
| `BREVO_TIMEOUT` | `15` | Délai HTTP Brevo (s) |
| `BREVO_SSL_VERIFY` | `false` | Vérification TLS (désactivée en local) |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASS` | repli `MAIL_*` | SMTP Brevo en secours (`smtp-relay.brevo.com`) |

### AquaSecure — site public & carte (`config/aquasecure.php`)

| Variable | Défaut | Rôle |
| :--- | :--- | :--- |
| `AQUASECURE_CACHE_TTL` | `120` | Cache des pages publiques (s) |
| `AQUASECURE_WATER_PHONE` / `AQUASECURE_WATER_EMAIL` / `AQUASECURE_WATER_ADDRESS` | *(valeurs du config)* | Coordonnées affichées sur le site |
| `AQUASECURE_MAP_LAT` | `46.2276` | Latitude du centre de la carte publique |
| `AQUASECURE_MAP_LNG` | `2.2137` | Longitude du centre de la carte publique |
| `AQUASECURE_MAP_ZOOM` | `6` | Niveau de zoom initial de la carte |

> **Tests** : certains tests (cycle de vie technicien / Brevo) exigent une clé Brevo même factice :
> PowerShell : `$env:BREVO_API_KEY='test-key'; php artisan test`

---

## 4. Base de Données : Entités, Migrations, Seeders, Rôles

### Rôles utilisateurs

| Rôle | Accès principal | Middleware |
| :--- | :--- | :--- |
| `admin` | Tout le back-office, `/admin/users`, signalements & incidents | `role:admin` |
| `manager` (alias `gestionnaire`) | Back-office `/back/*`, `/admin/*` (infra, maintenances, projets, financements, interventions, techniciens), IA | `role:admin,manager,gestionnaire` |
| `technician` | Espace « Mes interventions » (`/technicien/*`) + assistant IA (`/back/ai/chat`) | `role:technician…` |
| `citizen` (alias `citoyen`) | Site public authentifié : incidents, suivi, abonnements de zones | `role:citizen,citoyen` |

### Entités (14 modèles Eloquent)

| Entité (table) | Description & principaux champs |
| :--- | :--- |
| `User` | Comptes 4 rôles, profil, `telegram_chat_id` |
| `Zone` | Zones géographiques : `risk_level`, géolocalisation (`latitude`, `longitude`, `radius_m`), `image_path`, champs IA (`ai_risk_score`, `ai_risk_level`, `ai_analysis`, `ai_analyzed_at`) |
| `Infrastructure` | Stations de pompage, barrages, capteurs… liés à une zone, `status`, champs avancés |
| `Incident` | Incidents liés zone/infrastructure, `severity`, `status`, horodatages |
| `Signalement` | Signalements citoyens (site & Telegram), workflow incident, `user_id`, `tracking_token` (suivi public `/suivi`) |
| `Maintenance` | Cycle de vie maintenance (`reported` → `planned` → `in_progress` → `awaiting_validation` → `completed`, aussi `overdue`/`cancelled`), `technician_id`, validations |
| `MaintenanceAssignmentInvitation` | Invitation d'affectation technicien (jeton, expiration, accept/refus) |
| `MaintenanceIntelligenceScan` | Résultats des scans IA « Maintenance Intelligence » |
| `Technicien` | Profils techniciens (disponibilité, compétences) |
| `Intervention` | Missions sur incidents, équipe, `technician_id` |
| `Project` | Projets d'aménagement : budget, progression, statut |
| `Financement` | Enveloppes budgétaires par projet |
| `Alert` | Alertes capteurs/IA : `severity`, workflow (`acknowledged_at`, `resolved_at`), champs IA, index de performance |
| `UserZoneSubscription` | Abonnements citoyen ↔ zone (`/mes-zones`) |

### Migrations (32 fichiers, `database/migrations/`)

Évolutions notables, toutes **additives/nullable-safe** :

- socle : `users` (+`role`), `zones`, `infrastructures`, `incidents`, `projects`, `financements`, `interventions`, `alerts`, `signalements`, `maintenances`, `techniciens` ;
- métier : `technician_id` sur interventions, `severity` incidents, workflow signalements, champs avancés infra/maintenances ;
- IA : `add_ai_fields_to_zones_table`, `add_ai_fields_to_alerts_table`, `add_ai_intelligence_suite_fields`, `maintenance_intelligence_scans`, `maintenance_assignment_invitations` ;
- géolocalisation & UX : `add_geo_fields_to_zones_table`, workflow alertes (`acknowledged_at`/`resolved_at` + index), `image_path` zones, `tracking_token` signalements, `user_zone_subscriptions` ;
- Telegram : `add_telegram_to_users_table`.

### Seeders & factories

| Fichier | Rôle |
| :--- | :--- |
| `DatabaseSeeder` | 4 comptes de démonstration (4 rôles), zones, infrastructures, incidents, projets, financements, interventions, alertes |
| `ZoneSeeder` | 6 zones géolocalisées (2 infrastructures et 3 incidents chacune) — **jamais d'écrasement** des positions existantes |
| `InfrastructureMaintenanceSeeder` | Données d'infrastructures/maintenances |
| `database/factories/` | `UserFactory`, `ZoneFactory` (`risk_level` aléatoire → à fixer dans les tests), `IncidentFactory`, `AlertFactory` |

### Commandes de gestion de la base

```bash
php artisan migrate              # appliquer les migrations en attente
php artisan migrate:status       # état (Ran / Pending)
php artisan migrate:rollback --step=1   # annuler le dernier lot (une migration)
php artisan db:seed              # exécuter DatabaseSeeder
php artisan db:seed --class=ZoneSeeder  # exécuter un seeder précis
php artisan migrate --seed       # schéma + seeds sur une base neuve uniquement
```

---

## 5. Commandes Artisan & Guide Complet

### 5.1 Commandes spécifiques au projet

| Commande | Rôle |
| :--- | :--- |
| `php artisan serve [--port=8080] [--host=0.0.0.0] [--no-reload]` | Serveur de développement |
| `php artisan key:generate` | Génère `APP_KEY` |
| `php artisan storage:link` | Lien public vers `storage/app/public` (photos) |
| `php artisan migrate` / `migrate:status` / `migrate:rollback --step=1` | Migrations |
| `php artisan db:seed [--class=X]` | Données initiales / de test |
| `php artisan route:list [--path=x] [--name=y] [--except-vendor]` | Inspection des routes |
| `php artisan tinker` | Console interactive Eloquent |
| `php artisan optimize:clear` | Vide config + routes + vues + caches |
| `php artisan optimize` | Optimisation (production) |
| `php artisan cache:clear` / `config:clear` / `route:clear` / `view:clear` | Nettoyages ciblés |
| `php artisan down [--secret=…]` / `php artisan up` | Mode maintenance |
| `php artisan test [--filter=ZoneTest]` | Suite de tests PHPUnit |
| `vendor/bin/pint` | Correction du style de code (avant commit) |
| `php artisan queue:work` | Exécute les jobs (analyses IA `--queue`) |
| `php artisan ai:analyze-zones {--force} {--queue} {--limit=0} {--sleep=1}` | (Ré)analyse IA des zones en attente |
| `php artisan ai:analyze-alerts {--force} {--queue} {--limit=0} {--sleep=1}` | (Ré)analyse IA des alertes en attente |
| `php artisan ai:chat-check {--timeout=5}` | État du chatbot (Ollama/jetons disponibles) |
| `php artisan telegram:bot {--register} {--once}` | Bot Telegram (long polling) |

### 5.2 Créer de nouveaux composants (générateurs `make:`)

```bash
# Migration
php artisan make:migration create_sensors_table
php artisan make:migration add_status_to_zones_table --table=zones

# Modèle (Eloquent)
php artisan make:model Sensor          # modèle seul
php artisan make:model Sensor -m       # + migration
php artisan make:model Sensor -mc      # + migration + contrôleur
php artisan make:model Sensor -mcr     # + migration + contrôleur ressource
php artisan make:model Sensor -a       # tout : model, migration, controller, seeder, factory, policy

# Contrôleur
php artisan make:controller SensorController
php artisan make:controller SensorController --resource   # CRUD complet

# Requête de validation
php artisan make:request StoreIncidentRequest

# Seeder
php artisan make:seeder ZoneSeeder
```

### 5.3 Routes & inspection

```bash
php artisan route:list                        # méthode, URI, nom, contrôleur
php artisan route:list --path=incidents       # filtrer par chemin
php artisan route:list --name=back.           # filtrer par nom
php artisan route:list --except-vendor        # sans les routes vendor
```

### 5.4 Tinker — console interactive

```bash
php artisan tinker
```

```php
// Compter les incidents
App\Models\Incident::count();

// Premier projet avec ses financements
$projet = App\Models\Project::with('financements')->first();
$projet->name;

// Créer un utilisateur (valeurs d'exemple à remplacer)
App\Models\User::create([
    'name' => 'Nom Exemple',
    'email' => 'votre-email@exemple.com',
    'password' => bcrypt('votre-mot-de-passe'),
    'role' => 'admin',
]);

exit; // quitter
```

### 5.5 Cache & nettoyage

Après modification du `.env`, des routes ou de la configuration :

```bash
php artisan optimize:clear   # recommandé : vide tout d'un coup
```

```bash
php artisan cache:clear      # cache applicatif
php artisan config:clear     # cache de configuration
php artisan route:clear      # cache des routes
php artisan view:clear       # vues Blade compilées
php artisan optimize         # optimiser pour la production
```

### 5.6 Mode maintenance

```bash
php artisan down
php artisan down --secret="acces-prive-123"
# accès privé : http://127.0.0.1:8000/acces-prive-123
php artisan up
```

### 5.7 Récapitulatif express

| Action | Commande |
| :--- | :--- |
| **Serveur web** | `php artisan serve --no-reload` |
| **Styles/JS en direct** | `npm run dev` |
| **Build de production** | `npm run build` |
| **Base neuve + données de démo** | `php artisan migrate --seed` |
| **Appliquer les nouvelles migrations** | `php artisan migrate` |
| **État des tables** | `php artisan migrate:status` |
| **Lister les routes** | `php artisan route:list` |
| **Purger les caches** | `php artisan optimize:clear` |
| **Lancer la suite de tests** | `php artisan test` |
| **Backfill IA des zones/alertes** | `php artisan ai:analyze-zones` / `ai:analyze-alerts` |
| **Worker de file d'attente (IA)** | `php artisan queue:work` |
| **Bot Telegram** | `php artisan telegram:bot` |
| **Tester du code en direct** | `php artisan tinker` |

---

## 6. Fonctionnalités par Module

### 6.1 Zones

| | |
| :--- | :--- |
| **Front** | `GET /zones` (liste publique : KPIs, filtres `q`/risque/région/géolocalisation, tri, 12 par page), `GET /zones/{zone}` (fiche publique + carte + alertes), `GET /zones/map-data` (60 req/min), `GET /zones/nearest` (zone la plus proche, 60 req/min) |
| **Back** | `GET|POST|PUT|DELETE /back/zones` (CRUD, recherche, comptes, carte + formulaire synchronisé coordonnées, photos), `GET /back/zones/map-data`, `GET /back/zones/export` (CSV), `POST /back/zones/{zone}/ai-analyze` (throttle 20/min) |
| **Rôles** | Front public ; back : `admin`, `manager`/`gestionnaire` |
| **Fichiers** | `app/Http/Controllers/Front/FrontController.php` (`zones()`), `Front/PublicZoneAlertController.php`, `Back/ZoneController.php`, `resources/views/front/zones/*`, `resources/views/back/zones/*`, `resources/js/zone-map.js`, `resources/js/front-zones-alerts.js` |

### 6.2 Alertes

| | |
| :--- | :--- |
| **Front** | `GET /alerts` (+ alias `/alertes`) : filtres gravité/statut (`open`→`acknowledged`→`resolved`)/période dates/tri, KPIs, graphe 30 jours, 12 par page ; `GET /alerts?live=1` (JSON temps réel : KPIs + nouvelles alertes, 30 req/min par IP) ; `GET /alerts/{alert}` (fiche publique : explication + actions recommandées), `GET /alerts/live` (60 req/min) |
| **Back** | CRUD `/back/alerts`, actions workflow `acknowledge` / `resolve` / `reopen`, actions groupées `POST /back/alerts/bulk`, `GET /back/alerts/live` (120/min), `GET /back/alerts/export` (CSV), analyse IA (20/min) |
| **Rôles** | Front public ; back : `admin`, `manager`/`gestionnaire` (le citoyen est refusé sur export/live) |
| **Automatismes** | `IncidentObserver` / `ZoneObserver` créent des alertes (passage en risque élevé, incident `fuite`) ; analyse IA automatique à la création (`AI_AUTO_ANALYZE_ON_CREATE`) |
| **Fichiers** | `FrontController.php` (`alerts()`, `alertsChart()`), `Back/AlertController.php`, `app/Models/Alert.php` (statuts/sevérités), `resources/views/{front,back}/alerts/*` |

### 6.3 Incidents & signalements citoyens

- **Front (auth)** : `GET|POST /incidents` (création d'incident), `GET /incidents/{incident}`.
- **Signalements** : créés depuis le site (formulaire incidents, signalement de panne `POST /infrastructures/{infrastructure}/report-malfunction`) ou **Telegram** ; table `signalements` → confirmation en incident (`POST /back/signalements/{signalement}/confirm`).
- **Back** : `GET /back/incidents`, `POST /back/signalements/{signalement}/confirm` ; **admin** : `PATCH`/`DELETE` sur signalements et incidents (`/admin/*`).
- **Suivi citoyen** : `GET /suivi` — référence `SIG-0001` ou jeton public `tracking_token` (sans authentification).
- **Fichiers** : `FrontController.php`, `Back/BackController.php`, `app/Observers/IncidentObserver.php`, `resources/views/{front,back}/incidents/*`.

### 6.4 Infrastructures

- **Front** : `GET /infrastructures`, fiche `GET /infrastructures/{infrastructure}`, signalement de panne citoyen (`POST .../report-malfunction`, validation `ReportMalfunctionRequest`).
- **Back** : CRUD `GET|POST|PUT|DELETE /admin/infrastructures` (admin, manager/gestionnaire), recherche, validation des dates, rendu `novalidate` + messages d'erreur.
- **IA** : `POST /admin/infrastructures/{infrastructure}/ai-health` (analyse de santé, 20/min) + `GET …/ai-health` (résultat persisté).
- **Fichiers** : `InfrastructureController.php`, `app/Services/InfrastructureHealthAnalysisService.php`, `resources/views/{front,back}/infrastructures/*`.

### 6.5 Projets & Financements

- CRUD sous `/admin` (`admin`, `manager`/`gestionnaire`) : `projects` (budget, progression, statut) et `financements` (enveloppes par projet, statut `received`/`planned`).
- **Front** : `GET /projects`, fiche projet, `GET /funding`.
- **Fichiers** : `ProjectController.php`, `FinancementController.php`, `resources/views/{front,back}/{projects,funding,financements}/*`.

### 6.6 Interventions

- CRUD `/admin/interventions` (admin, manager) : liées à un incident, équipe, planification, statut, résultat, `technician_id`.
- **Front** : page projets/réseau ; le technicien voit ses opérations via son espace.
- **Fichiers** : `InterventionController.php`, `app/Models/Intervention.php`.

### 6.7 Techniciens & espace technicien

- CRUD `/admin/techniciens` (admin, manager) : profils, disponibilité.
- **Espace dédié** `/technicien/*` (rôles `technician`, `admin`) : `GET /technicien/interventions`, fiche, `POST .../complete` (soumission de rapport → `awaiting_validation`).
- **IA** : `POST /admin/maintenances/recommend-technicians` (matchmaker, 25/min) — recommandation filtrée/rangée et assainie des identifiants proposés.
- **Fichiers** : `TechnicienController.php`, `Technician/TechnicianInterventionController.php`, `app/Services/TechnicianRecommendationService.php`.

### 6.8 Maintenances & cycle de vie (avec e-mails Brevo)

- **Back** (`admin`, `manager`/`gestionnaire`) : CRUD `/admin/maintenances` + transitions `start`, `validate-completion`, `request-corrections`, `resend-invitation`.
- **Cycle de vie** : statuts `reported`, `overdue`, `planned`, `in_progress`, `awaiting_validation`, `completed`, `cancelled` (soumission technicien → `awaiting_validation`, validation → `completed`, corrections → retour `in_progress`) ; synchronisation automatique du statut de l'infrastructure.
- **Invitations technicien (Brevo)** : le manager invite un technicien → e-mail avec **jeton** → `GET /maintenances/invitation/{token}` + `POST …/accept|decline` ; échec Brevo ⇒ invitation conservée (`email_sent: false`), renvoi possible.
- **Tableau de bord manager** : validations en attente, invitations en cours, maintenances en cours, KPIs (`/dashboard/manager`).
- **IA** : centre `GET /admin/maintenance-intelligence` + `POST …/scan` (15/min) et `POST …/plan` (20/min), `POST …/store-maintenance` (confirmation crée la maintenance).
- **Fichiers** : `MaintenanceController.php`, `MaintenanceInvitationController.php`, `MaintenanceIntelligenceController.php`, `app/Services/{MaintenanceLifecycleService,BrevoEmailService,TechnicianAssignmentInvitationService,AiMaintenancePlannerService,MaintenanceRiskAnalysisService}.php`.

### 6.9 Cartes temps réel (Leaflet)

- **Front** : carte des zones sur les pages `/zones` (index et fiche `GET /zones/{zone}`), endpoints publics `map-data` + `nearest` (60/min), centre et zoom configurables (`AQUASECURE_MAP_*`).
- **Back** : panneau carte sur l'index et le détail des zones (`/back/zones/map-data`), formulaire avec carte synchronisée aux champs `latitude`/`longitude`/`radius_m` (validation bornes ±90/±180, rayon).
- **Sécurité** : `map-data` n'expose que des champs publics, interdit aux citoyens côté back ; aucune donnée personnelle géolocalisée.
- **Fichiers** : `resources/js/zone-map.js`, `resources/views/back/zones/_map.blade.php` (partiels carte back), inclusions carte dans `resources/views/front/zones/{index,show}.blade.php`, `ZoneController::mapData()`, `PublicZoneAlertController::mapData()/nearest()`.

### 6.10 Site public (front-office)

| Page | Route |
| :--- | :--- |
| État du réseau (accueil) | `GET /` |
| Zones / Alertes | `GET /zones`, `GET /alerts` (+ alias `/alertes`) |
| Suivi de signalement | `GET /suivi` |
| Incidents (auth) | `GET|POST /incidents`, `GET /incidents/{incident}` |
| Infrastructures | `GET /infrastructures`, fiche + signalement de panne |
| Projets / Financement | `GET /projects`, `GET /funding` |
| À propos / Services / Actualités / Contact | `GET /about`, `/services`, `/news`, `/contact` |
| Abonnements de zones (auth) | `GET /mes-zones`, `POST|DELETE /mes-zones/{zone}` |
| Authentification | `GET|POST /login`, `GET|POST /register`, `POST /logout`, `GET /dashboard` (redirige selon le rôle : admin → `/admin`, manager → `/dashboard/manager`, technician → `/technicien/interventions`, citizen → `/dashboard/citizen`) |
| Profil | `GET|PUT /profile` |

Design système partagé `aq-*` (cartes, badges de risque/gravité, statistiques, barres de filtres, pages) : `resources/css/{back,front}-zones-alerts.css`, composants `resources/views/components/*`.

### 6.11 Bot Telegram AquaSecure 🤖

Le bot permet aux citoyens de signaler un incident et de suivre leurs dossiers directement depuis Telegram, sans ouvrir le site.

#### Commandes disponibles

| Commande | Fonction |
| :--- | :--- |
| `/start` | Accueil et association du compte (par e-mail) |
| `/signaler` | Créer un nouveau signalement (type → description/photo → lieu → priorité → confirmation) |
| `/mes_signalements` | Consulter ses 10 derniers signalements |
| `/statut` | Vérifier l'avancement d'un dossier (`SIG-0012`, `12` ou `INC-0003`) |
| `/aide` | Afficher les commandes disponibles |
| `/annuler` | Annuler le formulaire en cours |

#### Mise en route

```bash
# 1. Créer un bot sur Telegram via @BotFather et copier le jeton

# 2. Ajouter le jeton dans le fichier .env
#    TELEGRAM_BOT_TOKEN=your_api_key

# 3. Appliquer la migration (colonne telegram_chat_id sur users)
php artisan migrate

# 4. Démarrer le bot en écoute (long polling, à laisser tourner dans un terminal)
php artisan telegram:bot
```

Le bot fonctionne en **long polling** : aucune URL publique ni webhook n'est nécessaire (pratique en local). Le menu de commandes est enregistré auprès de Telegram au démarrage.

#### Options utiles

```bash
php artisan telegram:bot --register   # enregistrer le menu puis quitter
php artisan telegram:bot --once       # traiter un seul lot de mises à jour (débogage)
```

#### Fonctionnement

- **Association du compte** : `/start` demande l'e-mail du compte AquaSecure ; le `chat_id` est enregistré sur l'utilisateur (`users.telegram_chat_id`).
- **Formulaire guidé** : la conversation est mémorisée en cache (`telegram:state:<chat_id>`, expiration 30 min, modifiable via `config/telegram.php`).
- **Création** : le signalement est enregistré dans la table `signalements` (statut `new`) et apparaît immédiatement dans le back-office (`/back/incidents`).
- **Photos** : envoyées à l'étape description → `storage/app/public/signalements/` (penser à `php artisan storage:link`).
- **Références** : `SIG-0001` (signalement) et `INC-0001` (incident lié).

#### Fichiers concernés

| Fichier | Rôle |
| :--- | :--- |
| `app/Console/Commands/TelegramBot.php` | Commande `telegram:bot` (boucle de polling) |
| `app/Services/Telegram/TelegramApi.php` | Client HTTP minimal de l'API Telegram |
| `app/Services/Telegram/TelegramBotService.php` | Logique des commandes et conversations |
| `app/Services/Telegram/TelegramException.php` | Exceptions métier Telegram |
| `config/telegram.php` | Jeton et délais (long polling, expiration de conversation) |

---

## 7. Intelligence Artificielle

### 7.1 Les 5 piliers

| Pilier | Description | Service |
| :--- | :--- | :--- |
| **Classification de signalements** | Proxy vers un service Flask externe : `POST /api/classification/predict`, contrôle `GET /api/classification/health` | `AIClassificationService` |
| **Analyse zones & alertes** | Classification de risque / enrichissement texte des zones et alertes, résultats persistés + cache | `ZoneAiService`, `AlertAiService`, `AiClient` |
| **AI Intelligence Suite** | Santé d'infrastructures, analyse de signalements citoyens (suggestions acceptables/éditables), matchmaker techniciens | `InfrastructureHealthAnalysisService`, `CitizenReportAnalysisService`, `TechnicianRecommendationService`, `AiIntelligenceSuiteController` |
| **Maintenance Intelligence** | Scan de risques (règles déterministes + IA Groq) et générateur de plan de maintenance (brouillon, sans écriture avant confirmation) | `MaintenanceRiskAnalysisService`, `AiMaintenancePlannerService`, `MaintenanceIntelligenceController` |
| **Assistant conversationnel** | Chatbot **lecture seule** sur zones et alertes | `ChatAiService`, `ChatTools`, `AiChatController` |

### 7.2 Configuration (`config/ai.php`)

- Fournisseur LLM : `AI_API_KEY` (repli `GROQ_API_KEY`), `AI_BASE_URL`/`AI_MODEL` (défaut : Groq, `openai/gpt-oss-120b`), `AI_TIMEOUT`, `AI_TEMPERATURE`, cache `AI_CACHE_TTL` (600 s), auto-analyse `AI_AUTO_ANALYZE_ON_CREATE`.
- Chatbot : section `chat` autonome — **jamais** la clé cloud : `CHAT_AI_*` (Ollama local, défaut `http://localhost:11434/v1`, `llama3.1:8b`).
- Classification : `services.ai_classification.url` (`AI_CLASSIFICATION_URL`, défaut `http://127.0.0.1:5000`).
- Aucune clé en dur ; exceptions métier `AiException` / `GroqAiException`.

### 7.3 Endpoints & limitations (throttle)

| Endpoint | Middleware | Rôle |
| :--- | :--- | :--- |
| `POST /back/zones/{zone}/ai-analyze` | `throttle:20,1` | Analyser une zone |
| `GET /back/zones/{zone}/ai-analysis` | auth + rôle back | Récupérer l'analyse persistée |
| `POST /back/alerts/{alert}/ai-analyze` | `throttle:20,1` | Analyser une alerte |
| `POST /admin/infrastructures/{id}/ai-health` | `throttle:20,1` | Santé d'infrastructure |
| `POST /admin/signalements/{id}/ai-analyze` | `throttle:20,1` | Analyse d'un signalement |
| `POST /admin/maintenances/recommend-technicians` | `throttle:25,1` | Recommandation de techniciens |
| `POST /admin/maintenance-intelligence/scan` | `throttle:15,1` | Scan de risques maintenance |
| `POST /admin/maintenance-intelligence/plan` | `throttle:20,1` | Génération de plan |
| `POST /back/ai/chat` | `throttle:15,1` | Message au chatbot |
| `GET /back/ai/chat/status` | `throttle:30,1` | État du chatbot |
| `POST /api/classification/predict` | groupe `api` | Classification (service Flask) |

Toutes ces routes exigent `auth` + les rôles appropriés (`admin`/`manager`/`gestionnaire`, + `technician` pour le chat ; citoyen et invité **refusés**).

### 7.4 Robustesse & replis

- **Repli déterministe** : si le LLM est absent, rate-limité ou renvoie du JSON invalide, un résultat local calculé (règles) est utilisé — testé par `ZoneAiTest`, `AlertAiTest`, `AiIntelligenceSuiteTest`, `MaintenanceIntelligenceTest`.
- **Cache** : analyses mises en cache (`AI_CACHE_TTL`), zéro requête IA inutile entre deux vues.
- **File d'attente** : `--queue` dispatch `AnalyzeZoneJob` / `AnalyzeAlertJob` (worker : `php artisan queue:work`).
- **Backfills** : `php artisan ai:analyze-zones` / `ai:analyze-alerts` (options `--force`, `--limit`, `--sleep`).
- **Chatbot** : si Ollama est hors ligne → routeur d'intention puis **repli mots-clés** ; commande `php artisan ai:chat-check` pour vérifier l'état.

### 7.5 Sécurité

- **Aucune donnée personnelle** n'est envoyée au fournisseur (testée : `ZoneAiTest::test_no_personal_data_is_sent_to_the_ai_provider`, `AiChatTest::test_no_personal_data_is_sent_to_the_model`).
- Chatbot : **whitelist stricte de 5 outils lecture seule** (`get_top_risk_zones`, `get_zone_summary`, `get_alerts`, `count_alerts`, `get_priority_alerts`), arguments bornés (`LIMIT_MAX = 20`, `PERIOD_MAX = 90`, énumération de sévérités), les appels ne **modifient jamais** les lignes zones/alertes.
- Limitation de débit sur tous les endpoints IA, contrôle par rôle, historique limité (`CHAT_AI_MAX_HISTORY`), outils refusés hors whitelist.

---

## 8. Routes & Contrôle d'Accès

### Groupes principaux

| Groupe | Middleware | Noms de routes | Exemples |
| :--- | :--- | :--- | :--- |
| Public | aucun | `home`, `front.*` | `/`, `/zones`, `/alerts`, `/suivi`, `/projects` |
| Authentifié | `auth` | `dashboard`, `profile.*`, `front.incidents.*`, `front.zones.subscribe*` | `/incidents`, `/mes-zones` |
| Back-office | `auth` + `role:admin,manager,gestionnaire`, préfixe `/back` | `back.*` | `back.zones.*`, `back.alerts.*`, `back.incidents` |
| Chat IA | `auth` + `role:admin,manager,gestionnaire,technician`, `/back` | `back.ai.chat` | `POST /back/ai/chat` |
| Admin | `auth` + `role:admin`, préfixe `/admin` | `admin.*` | `admin.users.*`, signalements/incidents |
| Ressources admin+manager | `auth` + `role:admin,manager,gestionnaire`, `/admin` | `infrastructures.*`, `maintenances.*`, `projects.*`, `financements.*`, `interventions.*`, `techniciens.*`, `maintenance-intelligence.*` | CRUD + IA |
| Espace technicien | `auth` + `role:technician,admin`, `/technicien` | `technicien.*` | `technicien.interventions.*` |
| API | groupe `api` | — | `POST /api/classification/predict`, `GET /api/classification/health` |
| Jetons (publics) | aucun | `maintenances.invitation.*` | `GET /maintenances/invitation/{token}` |

### Points d'attention

- **Ordre des routes** : les chemins statiques (`/back/zones/export`, `/back/alerts/live`, `/zones/map-data`, `/zones/nearest`) **précèdent** les routes `{zone}` / `{alert}` pour ne pas être avalés par la liaison implicite.
- **Alias** : `/alertes` redirige vers la même vue que `/alerts` (sans nom de route).
- **Throttles clés** : IA 15–25 req/min, chat 15/min (status 30/min), live/alertes public 60/min, export/live back 120/min.
- **Aliases de rôle** : `gestionnaire` ≡ `manager`, `citoyen` ≡ `citizen` (`app/Http/Middleware/RoleMiddleware.php`, enregistré comme alias `role` dans `bootstrap/app.php`).
- Inspection : `php artisan route:list` (voir §5.3).

---

## 9. Tests

```bash
php artisan test
# ou ciblé :
php artisan test --filter=ZoneTest
```

**Configuration** : PHPUnit `^11.5`, base **SQLite en mémoire** (`RefreshDatabase`), `Http::fake()` pour les appels IA/Brevo/Telegram, `withoutVite()` pour les tests de vues. Factories dans `database/factories/`.

**Dernier résultat local** : `176 passed, 3 failed (845 assertions)`.

- Les **3 échecs sont liés à l'environnement** : `TechnicianAssignmentLifecycleTest` exige une clé Brevo (demande HTTP Brevo non simulée sans `BREVO_API_KEY`).
  - Correction : `$env:BREVO_API_KEY='test-key'; php artisan test` (PowerShell) ou `BREVO_API_KEY=test-key php artisan test` (bash).

### Classes de tests (`tests/Feature/` + `tests/Unit/`)

| Classe | Couverture principale |
| :--- | :--- |
| `ZoneTest` | CRUD back zones, garde d'accès, doublons, suppression bloquée, photos, page front, alertes automatiques (risque élevé / fuite) |
| `AlertTest` | CRUD alertes, page zone, page front `/alerts`, observable à la création d'incident |
| `ZoneMapTest` | Validation géolocalisation, `map-data` (authentifié, interdit citoyen), carte dans formulaire/fiche, états sans coordonnées |
| `FrontZoneAlertTest` | Pages publiques zones/alertes (layout, carte, détail, explications), `map-data`/`nearest`/`live` publics, suivi par jeton, abonnements |
| `ZoneAlertUxTest` | Exports CSV, `live` back (KPIs), actions groupées, cycle acknowledge/resolve/reopen, filtres/tri/pagination back |
| `ZoneAiTest` | Analyse IA zones : auth, fallback, cache, queue, backfill, bouton IA, **aucune donnée personnelle envoyée** |
| `AlertAiTest` | Analyse IA alertes : auto-classification à la création, fallback JSON, regroupement, queue, backfill |
| `AiChatTest` | Chatbot : rôles, whitelist d'outils, routeur d'intention, fallback Ollama, throttle, `status`, historique, widget |
| `AiIntelligenceSuiteTest` | Santé infra IA, analyse de signalement (accepter/éditer), recommandation techniciens + assignation sécurisée |
| `MaintenanceIntelligenceTest` | Scan règles + IA, données manquantes sans invention, plan → édition → création de maintenance |
| `MaintenanceTest` | CRUD maintenances, logique overdue/statuts, validations chronologiques, rendu du formulaire |
| `TechnicianAssignmentLifecycleTest` | Invitations Brevo, acceptation, verrouillage, rapports, validation/corrections manager, dashboard (nécessite `BREVO_API_KEY`) |
| `InfrastructureTest` | CRUD + garde d'accès, recherche, signalement citoyen, validations de dates/noms |
| `PaginationTest` | Pagination propre front infrastructures + back maintenances |
| `ExampleTest` | Smoke test racine (Feature + Unit) |

---

## 10. Workflow Git

- **Dépôt** : `github.com/rayssen-draouil/Esprit_5twin4_elmono` — travail en **branches de fonctionnalité** fusionnées par **Pull Requests** (ex. branche en cours `feature/zones` ; PR #9 `Salem`, PR #10 `Adem`).
- **Convention de commits** : préfixe type conventional — `feat:`, `fix:`, `test:`, `docs:` (ex. `feat: AI Maintenance Intelligence Suite…`).

### Routine recommandée

```bash
git status                      # état du travail
git diff                        # modifications détaillées
git checkout -b feature/xxx     # nouvelle branche
git add <fichiers>              # ne jamais git add -A aveuglement
git commit -m "feat: description"
git pull --rebase               # avant push
git push -u origin feature/xxx  # puis ouvrir la PR
```

### Avant chaque push

```bash
vendor/bin/pint        # style de code
php artisan test       # suite verte
```

### Règles

- **Ne jamais committer** `.env`, clés, jetons ou mots de passe — placeholders uniquement (`your_api_key`).
- Migrations **additives/nullable** de préférence ; exécuter `php artisan migrate:status` pour vérifier l'état.
- Pas de `git commit --amend`/force-push sur une branche partagée ; en cas d'échec, nouveau commit de correction.

---

## 11. Dépannage (Troubleshooting)

| Symptôme | Cause probable | Solution |
| :--- | :--- | :--- |
| Port 8000 occupé | Autre serveur actif | `php artisan serve --port=8080` |
| Serveur lent sous Windows | Rechargement de fichiers | `php artisan serve --no-reload` |
| CSS/JS manquants ou obsolètes | Vite non lancé / build périmé | `npm run dev` ou `npm run build` ; vérifier `vite.config.js` (`inputs`) |
| Changements `.env` ignorés | Cache de configuration | `php artisan optimize:clear` |
| Photos (zones/signalements) en 404 | Lien symbolique absent | `php artisan storage:link` |
| « Migration pending » | Tables absentes | `php artisan migrate` puis `php artisan migrate:status` |
| Tests : 3 échecs `TechnicianAssignmentLifecycleTest` | `BREVO_API_KEY` absente | `$env:BREVO_API_KEY='test-key'; php artisan test` |
| Analyse IA jamais déclenchée en différé | Worker non lancé | `php artisan queue:work` |
| Analyse IA en erreur/rate-limit | Pas de clé ou quota | Renseigner `AI_API_KEY`/`GROQ_API_KEY` ; repli déterministe automatique |
| Chatbot toujours en « mots-clés » | Ollama absent (défaut local) | Installer Ollama + `llama3.1:8b`, vérifier `CHAT_AI_BASE_URL` ; `php artisan ai:chat-check` |
| E-mail d'invitation non envoyé | Clé Brevo absente | Renseigner `BREVO_API_KEY` (+ `BREVO_SENDER_*`) ; relancer via « renvoyer l'invitation » |
| Bot Telegram muet | Jeton absent/incorrect | `TELEGRAM_BOT_TOKEN` dans `.env`, redémarrer `php artisan telegram:bot` |
| 403 sur une route back | Rôle insuffisant | Vérifier le `role` de l'utilisateur et les alias (`gestionnaire`, `citoyen`) — §8 |
| Formulaire zone sans carte | Coordonnées invalides | Vérifier bornes latitude ±90 / longitude ±180 et rayon |
| Base à remettre à zéro en local | — | Supprimer le fichier SQLite puis `php artisan migrate --seed` (base neuve uniquement) |

---

## 12. Structure du Projet

```
Esprit_5twin4_elmono/
├── app/
│   ├── Console/Commands/        # AnalyzeAlerts, AnalyzeZones, ChatCheck, TelegramBot
│   ├── Exceptions/              # AiException, GroqAiException
│   ├── Http/
│   │   ├── Controllers/         # Auth, Dashboard, Back/, Front/, Api/, CRUD métier,
│   │   │                        # MaintenanceInvitation, Technician/
│   │   ├── Middleware/          # RoleMiddleware (alias role)
│   │   └── Requests/            # 16 FormRequests de validation
│   ├── Jobs/                    # AnalyzeZoneJob, AnalyzeAlertJob (queue)
│   ├── Models/                  # 14 modèles Eloquent
│   ├── Observers/               # AlertObserver, IncidentObserver, ZoneObserver
│   ├── Providers/               # AppServiceProvider
│   ├── Services/
│   │   ├── AI/                  # AiClient, ZoneAiService, AlertAiService,
│   │   │                        # ChatAiService, ChatTools
│   │   ├── Telegram/            # TelegramApi, TelegramBotService, TelegramException
│   │   └── *.php                # Brevo, Groq, Maintenance, Techniciens, Classification…
│   └── Support/                 # CitizenStatus
├── bootstrap/app.php            # alias de middleware, groupes de routes
├── config/                      # ai.php, aquasecure.php, telegram.php, services.php…
├── database/
│   ├── factories/               # User, Zone, Incident, Alert
│   ├── migrations/              # 32 migrations
│   └── seeders/                 # DatabaseSeeder, ZoneSeeder, InfrastructureMaintenanceSeeder
├── public/
├── resources/
│   ├── css/                     # app.css, back-zones-alerts.css, front-zones-alerts.css,
│   │                            # enhancements, premium-overrides, signalement
│   ├── js/                      # app.js, bootstrap.js, zone-map.js, zones-alerts.js,
│   │                            # front-zones-alerts.js, ai-classification.js, infra-validation.js
│   └── views/
│       ├── components/          # badges de risque/gravité, cartes, statistiques, filtres… (x-*)
│       ├── dashboards/          # manager, citoyen
│       ├── layouts/             # back, front, auth
│       ├── partials/back/       # sidebar, topbar, flash, ai-chat
│       ├── partials/front/      # header, footer, flash
│       ├── back/                # alerts, zones, incidents, infrastructures, projects, funding, users…
│       └── front/               # home, zones, alerts, incidents, infrastructures, projects, funding, news…
├── routes/                      # web.php, api.php, console.php
├── storage/
├── tests/
│   ├── Feature/                 # 15 classes (zones, alertes, IA, Telegram-adjacent, maintenance…)
│   └── Unit/
├── .env.example                 # toutes les variables documentées (§3)
├── composer.json                # Laravel 12, PHPUnit 11, Pint…
├── package.json                 # Vite 6, Tailwind 4, Chart.js, Leaflet
├── vite.config.js               # points d'entrée CSS/JS
└── README.md                    # ce document
```

---

## 13. Crédits

- **Projet** : AquaSecure — module **5TWIN**, **ESPRIT School of Engineering**.
- **Framework & outils** : [Laravel](https://laravel.com) 12, [PHPUnit](https://phpunit.de) 11, [Laravel Pint](https://laravel.com/docs/pint), [Vite](https://vite.dev), [Tailwind CSS](https://tailwindcss.com) 4.
- **Frontend** : [Chart.js](https://www.chartjs.org) (graphiques), [Leaflet](https://leafletjs.com) (cartes), Axios.
- **Services** : [Groq](https://groq.com) (LLM compatible OpenAI), [Ollama](https://ollama.com) (chatbot local), [Brevo](https://www.brevo.com) (e-mails transactionnels), [Telegram Bot API](https://core.telegram.org/bots/api), service de classification Flask (séparé).
- **Design système** : composants Blade `x-*` et classes `aq-*` partagés entre back-office et site public.

---

*Projet réalisé dans le cadre du module 5TWIN — ESPRIT.*
