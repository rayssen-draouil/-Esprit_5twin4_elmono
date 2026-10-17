# 🌊 AquaSecure — Guide & Répertoire des Commandes Laravel

Bienvenue sur le projet **AquaSecure** (Esprit 5TWIN).
Ce guide documente l'ensemble des commandes essentielles pour démarrer, développer, gérer la base de données et administrer le projet avec Laravel 12 et Artisan.

---

## 📑 Sommaire
1. [Démarrage & Lancement du Projet](#1-démarrage--lancement-du-projet)
2. [Configuration Initiale de l'Environnement](#2-configuration-initiale-de-lenvironnement)
3. [Migrations & Gestion de la Base de Données](#3-migrations--gestion-de-la-base-de-données)
4. [Création de Nouveaux Composants (Models, Controllers, etc.)](#4-création-de-nouveaux-composants)
5. [Seeders & Données de Test](#5-seeders--données-de-test)
6. [Routes & Inspection](#6-routes--inspection)
7. [Tinker — Console Interactive](#7-tinker--console-interactive)
8. [Cache & Nettoyage](#8-cache--nettoyage)
9. [Mode Maintenance](#9-mode-maintenance)
10. [Récapitulatif Express Spécifique au Projet](#10-récapitulatif-express-spécifique-au-projet)

---

## 1. Démarrage & Lancement du Projet

### Lancer le serveur backend Laravel
```bash
php artisan serve
```
*Accessible par défaut sur :* `http://127.0.0.1:8000`

#### Options utiles :
```bash
# Changer le port si le port 8000 est déjà occupé
php artisan serve --port=8080

# Autoriser l'accès depuis d'autres appareils du réseau local
php artisan serve --host=0.0.0.0 --port=8000

# Mode sans surveillance de fichiers (recommandé sur Windows si le serveur ralentit)
php artisan serve --no-reload
```

### Lancer le serveur frontend Vite (Hot-Reload / Rafraîchissement direct)
Dans un deuxième terminal :
```bash
npm run dev
```

### Compiler les assets pour la production
```bash
npm run build
```

---

## 2. Configuration Initiale de l'Environnement

Lorsque vous clonez le projet ou le récupérez sur une nouvelle machine :

```bash
# 1. Installer les dépendances PHP
composer install

# 2. Créer le fichier d'environnement s'il n'existe pas
cp .env.example .env

# 3. Générer la clé secrète de l'application (APP_KEY)
php artisan key:generate

# 4. Installer les dépendances JavaScript / CSS
npm install

# 5. Compiler les assets
npm run build

# 6. Créer le lien symbolique pour le stockage de fichiers (photos, uploads)
php artisan storage:link
```

---

## 3. Migrations & Gestion de la Base de Données

Les migrations sont le « versioning » de votre schéma de base de données.

### Exécuter les migrations en attente
Applique uniquement les nouvelles migrations non encore exécutées :
```bash
php artisan migrate
```

### Voir le statut des migrations
Affiche la liste de toutes les migrations et indique si elles ont été appliquées (`Ran`) ou non (`Pending`) :
```bash
php artisan migrate:status
```

### Annuler la dernière série de migrations (Rollback)
Annule les migrations du dernier batch :
```bash
php artisan migrate:rollback
```

Annuler un nombre précis de migrations :
```bash
php artisan migrate:rollback --step=1
```

### Réinitialiser et tout rejouer (Reset / Refresh)
Annule toutes les migrations puis les réexécute :
```bash
php artisan migrate:refresh
```

### Remise à zéro complète (Fresh — Le plus utilisé en développement)
Supprime toutes les tables de la base de données et réexécute l'intégralité des migrations depuis le début :
```bash
php artisan migrate:fresh
```

### Remise à zéro complète + Remplissage avec les données de test (Seeders)
```bash
php artisan migrate:fresh --seed
```

---

## 4. Création de Nouveaux Composants

Artisan fournit le générateur `make:` pour créer rapidement des classes en respectant les conventions Laravel.

### Créer une nouvelle table (Migration)
```bash
# Créer une nouvelle table (ex: sensors)
php artisan make:migration create_sensors_table

# Modifier une table existante pour lui ajouter une colonne
php artisan make:migration add_status_to_zones_table --table=zones
```

### Créer un Modèle Eloquent (Model)
```bash
# Modèle simple
php artisan make:model Sensor

# Modèle + Migration (-m)
php artisan make:model Sensor -m

# Modèle + Migration + Controller (-mc)
php artisan make:model Sensor -mc

# Modèle + Migration + Controller de ressource CRUD complet (-mcr)
php artisan make:model Sensor -mcr

# Modèle complet (-a pour all : model, migration, controller, seeder, factory, policy)
php artisan make:model Sensor -a
```

### Créer un Contrôleur (Controller)
```bash
# Contrôleur vide
php artisan make:controller SensorController

# Contrôleur avec méthodes CRUD pré-remplies (index, create, store, show, edit, update, destroy)
php artisan make:controller SensorController --resource
```

### Créer une requête de validation (Form Request)
Permet de séparer la logique de validation des formulaires du contrôleur :
```bash
php artisan make:request StoreIncidentRequest
```

---

## 5. Seeders & Données de Test

Les seeders permettent de remplir automatiquement la base de données avec des enregistrements initiaux ou de test.

### Créer un Seeder
```bash
php artisan make:seeder ZoneSeeder
```

### Exécuter les Seeders
```bash
# Exécute la classe principale Database\Seeders\DatabaseSeeder
php artisan db:seed

# Exécuter un seeder précis
php artisan db:seed --class=ZoneSeeder
```

---

## 6. Routes & Inspection

### Lister toutes les routes de l'application
Affiche la méthode HTTP (GET, POST, PUT, DELETE), l'URI, le nom de route et le contrôleur associé :
```bash
php artisan route:list
```

### Filtrer les routes
```bash
# Filtrer par chemin
php artisan route:list --path=incidents

# Filtrer par nom
php artisan route:list --name=back.

# Lister uniquement les routes sans le middleware interne
php artisan route:list --except-vendor
```

---

## 7. Tinker — Console Interactive

Tinker est un REPL interactif permettant de tester du code PHP et manipuler les modèles en direct sans recharger une page web.

### Ouvrir Tinker
```bash
php artisan tinker
```

### Exemples d'utilisation dans Tinker :
```php
// Compter les incidents
App\Models\Incident::count();

// Récupérer le premier projet avec ses financements
$projet = App\Models\Project::with('financements')->first();
$projet->name;

// Créer un nouvel utilisateur rapidement
App\Models\User::create([
    'name' => 'Mohamed',
    'email' => 'mohamed@aquasecure.fr',
    'password' => bcrypt('password123'),
    'role' => 'admin'
]);

// Quitter Tinker
exit;
```

---

## 8. Cache & Nettoyage

Lorsque vous modifiez les fichiers `.env`, les routes ou les configurations et que les changements ne semblent pas pris en compte :

### Tout nettoyer d'un coup (Recommandé)
```bash
php artisan optimize:clear
```
*Cette commande vide simultanément le cache de configuration, de routes, de vues et d'événements.*

### Commandes individuelles :
```bash
# Vider le cache de l'application
php artisan cache:clear

# Vider le cache de configuration
php artisan config:clear

# Vider le cache des routes
php artisan route:clear

# Vider le cache des vues Blade
php artisan view:clear
```

### Optimiser pour la production
```bash
php artisan optimize
```

---

## 9. Mode Maintenance

Pour suspendre temporairement le site lors d'une mise à jour :

### Mettre le site en maintenance
```bash
php artisan down
```

Option avec clé secrète pour continuer à tester le site en privé :
```bash
php artisan down --secret="acces-prive-123"
# Accès via : http://127.0.0.1:8000/acces-prive-123
```

### Remettre le site en ligne
```bash
php artisan up
```

---

## 10. Récapitulatif Express Spécifique au Projet

| Action | Commande |
| :--- | :--- |
| **Démarrer le serveur web** | `php artisan serve --no-reload` |
| **Démarrer les styles/JS en direct** | `npm run dev` |
| **Reconstruire la BD de zéro + données** | `php artisan migrate:fresh --seed` |
| **Appliquer une nouvelle migration** | `php artisan migrate` |
| **Voir l'état des tables** | `php artisan migrate:status` |
| **Lister les URLs / Routes** | `php artisan route:list` |
| **Purger tous les caches après modif .env** | `php artisan optimize:clear` |
| **Démarrer le bot Telegram** | `php artisan telegram:bot` |
| **Tester du code en direct** | `php artisan tinker` |

---

## 11. Bot Telegram AquaSecure 🤖

Le bot permet aux citoyens de signaler un incident et de suivre leurs dossiers directement depuis Telegram, sans ouvrir le site.

### Commandes disponibles

| Commande | Fonction |
| :--- | :--- |
| `/start` | Accueil et association du compte (par e-mail) |
| `/signaler` | Créer un nouveau signalement (type → description/photo → lieu → priorité → confirmation) |
| `/mes_signalements` | Consulter ses 10 derniers signalements |
| `/statut` | Vérifier l'avancement d'un dossier (`SIG-0012`, `12` ou `INC-0003`) |
| `/aide` | Afficher les commandes disponibles |
| `/annuler` | Annuler le formulaire en cours |

### Mise en route

```bash
# 1. Créer un bot sur Telegram via @BotFather et copier le jeton

# 2. Ajouter le jeton dans le fichier .env
#    TELEGRAM_BOT_TOKEN=123456789:AAF...

# 3. Appliquer la migration (colonne telegram_chat_id sur users)
php artisan migrate

# 4. Démarrer le bot en écoute (long polling, à laisser tourner dans un terminal)
php artisan telegram:bot
```

Le bot fonctionne en **long polling** : aucune URL publique ni webhook n'est nécessaire (pratique en local).
Le menu de commandes est automatiquement enregistré auprès de Telegram au démarrage.

### Options utiles

```bash
# Enregistrer uniquement le menu de commandes puis quitter
php artisan telegram:bot --register

# Traiter un seul lot de mises à jour puis quitter (débogage)
php artisan telegram:bot --once

# Mode test utilisable depuis l'interface d'admin Telegram (@BotFather)
```

### Fonctionnement

- **Association du compte** : `/start` demande l'e-mail du compte AquaSecure ; le `chat_id` Telegram est enregistré sur l'utilisateur (`users.telegram_chat_id`).
- **Formulaire guidé** : la conversation est mémorisée en cache (`telegram:state:<chat_id>`, expiration 30 min, modifiable via `config/telegram.php`).
- **Création** : le signalement est enregistré dans la même table que le formulaire du site (`signalements`, statut `new`), il apparaît donc immédiatement dans le back-office (`/back/incidents`).
- **Photos** : une photo envoyée pendant l'étape description est téléchargée dans `storage/app/public/signalements/` (penser à `php artisan storage:link`).
- **Références** : `SIG-0001` (signalement) et `INC-0001` (incident lié).

### Fichiers concernés

| Fichier | Rôle |
| :--- | :--- |
| `app/Console/Commands/TelegramBot.php` | Commande Artisan `telegram:bot` (boucle de polling) |
| `app/Services/Telegram/TelegramApi.php` | Client HTTP minimal de l'API Telegram |
| `app/Services/Telegram/TelegramBotService.php` | Logique des commandes et conversations |
| `config/telegram.php` | Jeton et délais (long polling, expiration de conversation) |

---

### 🗄️ Entités Principales de la Base AquaSecure :
- `User` : Utilisateurs (`admin`, `manager`, `technician`, `citizen`)
- `Zone` : Zones géographiques et niveaux de risque
- `Infrastructure` : Équipements de traitement, barrages, stations de pompage
- `Incident` : Alertes et signalements d'incidents
- `Project` : Projets d'infrastructures et d'aménagement
- `Financement` : Enveloppes budgétaires et subventions
- `Intervention` : Missions de techniciens sur les incidents
- `Alert` : Notifications de capteurs et alertes en temps réel

---
*Projet réalisé dans le cadre du module 5TWIN — ESPRIT.*
