Loto Quine/Bingo – Admin & Affichage Public (Symfony 7.4)

Application Symfony 7.4 pour gérer un loto/quine (administration) et un affichage public en temps réel via Mercure.

Ce document explique l’installation, l’exécution, les tests et les conventions du projet. Pour les détails fonctionnels complets, consultez `doc/spec.md`.

1) Périmètre et pile technique
- PHP: >= 8.2 (CLI de dev 8.3)
- Symfony: 7.4.*
- Base de données: PostgreSQL 16 (Docker Compose)
- Temps réel: Mercure Hub (image `dunglas/mercure`)
- Mail de dev: Mailpit

Les variables d’environnement par défaut sont dans `.env`. Personnalisez via `.env.local` (ignoré par Git).

2) Prérequis
- PHP 8.2+ et Composer
- Docker + Docker Compose
- (Optionnel) Symfony CLI `symfony`

3) Installation
1. Dépendances PHP
   composer install

2. Environnement
   - Ajustez `.env.local` si nécessaire.
   - Points clés: `DATABASE_URL`, `MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET`.

3. Démarrer l’infra de dev
   docker compose up -d database mercure mailer
   - Postgres: port exposé 5432 (via `compose.override.yaml`).
   - Mercure: HTTPS par défaut via Caddy. Pour simplifier localement, vous pouvez définir `SERVER_NAME=':80'` dans le service `mercure` (voir `compose.yaml`) et passer `MERCURE_URL`/`MERCURE_PUBLIC_URL` en `http://`.
   - Assurez-vous que `MERCURE_JWT_SECRET` (app) correspond aux clés du hub dans Compose.

4. Base de données
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate -n
   # Si Messenger utilise le transport Doctrine (auto_setup=0)
   php bin/console messenger:setup-transports

4) Lancer l’application
- Serveur PHP intégré
  php -S 127.0.0.1:8001 -t public
- ou via Symfony CLI
  symfony serve -d

URLs pratiques (dev):
- Application: http://127.0.0.1:8001
- Mailpit UI: http://127.0.0.1:8025
- Mercure Hub: http://127.0.0.1 (port selon configuration; avec `SERVER_NAME=':80'`, port 80)

5) Tests

Le projet utilise une approche complète avec **tests unitaires** (PHPSpec) et **tests fonctionnels** (Behat).

PHPSpec (Tests unitaires)
- Framework de tests unitaires orienté spécification (BDD)
- Config: `phpspec.yml`
- Lancer: ./vendor/bin/phpspec run
- Specs dans `spec/` (miroir de `src/`)
- Tests rapides et isolés pour la logique métier
- Exemple: `spec/Service/WinnerExportServiceSpec.php`

Behat (Tests fonctionnels)
- Framework de tests comportementaux en langage naturel (Gherkin)
- Config: `behat.yml.dist` (créez `behat.yml` local pour overrides si besoin)
- Exécution: composer behat ou vendor/bin/behat
- Scénarios dans `features/*.feature`
- Contexts de test dans `tests/Behat/`
- Session par défaut: kernel Symfony via BrowserKit (pas de serveur externe)
- Pour la DB en scénarios: utilisez `APP_ENV=test`, base dédiée, purge entre scénarios, fixtures côté Contexts
- Ne dépendez pas du hub Mercure; validez l'état via HTTP/DOM

Base de test
- Postgres dédié via `.env.test.local`
  DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app_test?serverVersion=16&charset=utf8"
  Puis:
  php bin/console doctrine:database:create --env=test
  php bin/console doctrine:migrations:migrate -n --env=test
- SQLite fichier (rapide, recommandé pour les tests) via `.env.test.local`
  DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_test.db"

6) Structure du projet
```
assets/               JS/CSS (AssetMapper + Stimulus)
bin/                  Commandes console (console, phpunit)
config/               Configuration Symfony (services, routes, packages)
doc/                  Spécifications (voir spec.md)
features/             Scénarios Behat (tests fonctionnels)
migrations/           Migrations Doctrine
public/               Point d'entrée web (index.php)
src/
  ├── Command/        Commandes CLI (ImportCardsCommand, SeedDemoCommand)
  ├── Controller/     Contrôleurs HTTP
  │   ├── Admin/      Zone d'administration
  │   ├── AdminController.php
  │   ├── PublicController.php
  │   └── SecurityController.php
  ├── Entity/         Entités Doctrine
  │   ├── Card.php    Cartons de loto (référence + grille 3x5)
  │   ├── Draw.php    Numéros tirés (1-90)
  │   ├── Event.php   Événements (soirées loto)
  │   ├── Game.php    Parties d'un événement (règle + dotation)
  │   ├── Player.php  Joueurs (nom, email, téléphone)
  │   └── Winner.php  Gagnants (lien vers Card et Game)
  ├── Enum/           Énumérations PHP 8.1+
  │   ├── BlockedReason.php   (WINNER)
  │   ├── GameStatus.php      (PENDING, RUNNING, FINISHED)
  │   ├── RuleType.php        (QUINE, DOUBLE_QUINE, FULL_CARD)
  │   └── WinnerSource.php    (SYSTEM, OFFLINE)
  ├── Form/           Formulaires Symfony
  ├── Repository/     Repositories Doctrine
  └── Service/        Logique métier
      ├── CardService.php              Gestion des cartons
      ├── DrawService.php              Tirages de numéros
      ├── WinnerDetectionService.php   Détection automatique
      ├── WinnerService.php            Validation des gagnants
      └── WinnerExportService.php      Export CSV des gagnants
spec/                 Specs PHPSpec (tests unitaires)
templates/            Vues Twig (admin/public)
tests/                Behat Contexts (tests fonctionnels)
translations/         Fichiers de traduction
```

7) Modèle de données
Entités principales et relations:

**Event** (Événement/Soirée)
- `id`, `name`, `date`
- Relations: `games` (OneToMany Game), `players` (OneToMany Player)

**Game** (Partie)
- `id`, `position`, `rule` (RuleType), `prize`, `status` (GameStatus)
- `isFrozen` (bool), `freezeOrderIndex` (int|null) - Gel automatique au gain
- Relations: `event` (ManyToOne Event), `draws` (OneToMany Draw), `winners` (OneToMany Winner)

**Player** (Joueur)
- `id`, `name`, `email`, `phone`, `notes`
- Relations: `event` (ManyToOne Event), `cards` (OneToMany Card)

**Card** (Carton)
- `id`, `reference`, `grid` (JSON: 3 lignes x 5 numéros)
- `isBlocked` (bool), `blockedAt` (DateTimeImmutable|null), `blockedReason` (BlockedReason|null)
- Relations: `player` (ManyToOne Player)
- Note: La relation avec Event se fait via Player
- Note: Un carton bloqué ne peut plus gagner jusqu'à la démarque

**Draw** (Tirage)
- `id`, `number` (1-90), `orderIndex`, `createdAt`
- Relations: `game` (ManyToOne Game)
- Contrainte unique: (game_id, number)

**Winner** (Gagnant)
- `id`, `source` (WinnerSource), `reference`, `createdAt`
- `winningOrderIndex` (int) - Index du tirage déclencheur
- Relations: `game` (ManyToOne Game), `card` (ManyToOne Card)

8) Conventions & règles métier
- **Code**: PSR-12, bonnes pratiques Symfony. Contrôleurs fins, logique métier dans des services testables.
- **Doctrine**: Entités/repos sous `src/Entity` et `src/Repository`, schéma via migrations.
- **Mercure**: Topics stables (`/events/{id}/draws`, `/events/{id}/public`), CORS aligné sur l'URL de l'app, tolérance aux replays/déconnexions.
- **Messenger**: Transport Doctrine (`auto_setup=0`). Pour async: créer les tables et lancer un worker `php bin/console messenger:consume -vv`.
- **Base de données**: Transactions explicites autour des tirages/validation gagnants, pas de hard-delete (audit par timestamps).
- **Règles gagnants**:
  - `QUINE`: ≥ 1 ligne complète (5 numéros)
  - `DOUBLE_QUINE`: ≥ 2 lignes complètes (10 numéros)
  - `FULL_CARD`: 3 lignes complètes (15 numéros - carton plein)
  - Gagnants « potentiels » (détection automatique) jusqu'à validation admin
  - Support `OFFLINE` avec référence libre (saisie manuelle)
- **Gel automatique**:
  - Dès qu'un gagnant est détecté, la partie est gelée automatiquement
  - Le gel enregistre l'orderIndex exact du tirage gagnant
  - Aucun nouveau tirage n'est autorisé tant que la partie est gelée
  - Seuls les cartons gagnants au même numéro peuvent être validés
- **Blocage des cartons**:
  - Un carton gagnant validé est automatiquement bloqué
  - Un carton bloqué n'apparaît plus dans les gagnants potentiels
  - Le déblocage se fait uniquement par démarque (reset) de la partie
- **Workflow**:
  - Statuts de partie: `PENDING` → `RUNNING` → `FINISHED`
  - Passer au jeu suivant bascule les statuts automatiquement
  - Démarrage d'un nouveau tirage possible uniquement en statut `RUNNING` et si la partie n'est pas gelée
  - Démarque (reset) disponible pour recommencer une partie : supprime les tirages, dégèle la partie, débloque les cartons et supprime les gagnants

9) Commandes utiles

**Base de données**
```bash
php bin/console doctrine:database:create           # Créer la base
php bin/console doctrine:migrations:diff           # Générer une migration
php bin/console doctrine:migrations:migrate -n     # Appliquer les migrations
php bin/console messenger:setup-transports         # Créer les tables Messenger
```

**Développement**
```bash
php bin/console cache:clear                        # Vider le cache
php bin/console debug:container                    # Lister les services
php bin/console debug:router                       # Lister les routes
php bin/console app:seed-demo                      # Créer données de démo
php bin/console app:import-cards <fichier>         # Importer des cartons
```

**Tests & qualité**
```bash
./vendor/bin/phpspec run                           # Tests unitaires (PHPSpec)
./vendor/bin/behat                                 # Tests fonctionnels (Behat)
composer behat                                     # Alias Behat avec progress
./vendor/bin/phpspec run && ./vendor/bin/behat     # Tous les tests
composer cs:check                                  # Vérifier le style de code
composer cs:fix                                    # Corriger le style de code
```

10) Dépannage rapide
- **Mercure ne reçoit pas d'événements**: Vérifier que `MERCURE_JWT_SECRET` correspond aux clés du hub et que la config CORS/URL publique est correcte.
- **Erreurs DB en tests**: S'assurer que la base de test est créée et migrée, ou utiliser SQLite en mémoire.
- **Actifs/front**: AssetMapper actif; `assets:install` est géré par Flex lors de l'installation.
- **Erreurs de permissions**: Vérifier les permissions sur `var/cache` et `var/log`.
- **Doctrine proxy/cache**: Supprimer `var/cache/dev/doctrine` en cas d'incohérence.

11) Fonctionnalités principales

**Administration** (`/admin`)
- Gestion des événements (CRUD)
- Gestion des parties d'un événement (ordre, règle, dotation)
- Gestion des joueurs et attribution de cartons
- Interface de tirage en temps réel avec gel automatique
- Détection automatique des gagnants potentiels (excluant les cartons bloqués)
- Validation des gagnants système avec blocage automatique du carton
- Saisie manuelle de gagnants offline avec gel de la partie
- Navigation entre parties (précédent/suivant) avec conservation des tirages
- Démarque d'une partie : reset complet (tirages, gel, blocages, gagnants)
- Recherche et pagination des cartons
- **Export CSV des gagnants** : export complet des gagnants d'un événement (partie, règle, prix, joueur, téléphone, email, source, date)

**Affichage public** (`/events/{id}/public`)
- Vue en temps réel des tirages via Mercure
- Affichage de la partie en cours
- Historique des numéros tirés
- Liste des gagnants validés
- Mise à jour automatique sans rechargement

**Authentification**
- Connexion sécurisée avec CSRF
- Gestion de session
- Protection de la zone admin

12) Liens
- **Spécification**: `doc/spec.md`
- **Symfony**: https://symfony.com/
- **Mercure**: https://mercure.rocks/
- **Mailpit**: https://github.com/axllent/mailpit
- **Doctrine**: https://www.doctrine-project.org/
