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
PHPUnit
- Config: `phpunit.dist.xml` (bootstrap `tests/bootstrap.php`).
- Lancer: ./vendor/bin/phpunit
- Base de test au choix:
  1) Postgres dédié via `.env.test.local`
     DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app_test?serverVersion=16&charset=utf8"
     Puis:
     php bin/console doctrine:database:create --env=test
     php bin/console doctrine:migrations:migrate -n --env=test
  2) SQLite fichier (rapide) via `.env.test.local`
     DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_test.db"

Behat
- Dépendances présentes en require-dev.
- Config: `behat.yml.dist` (créez `behat.yml` local pour overrides si besoin).
- Exécution: composer behat ou vendor/bin/behat.
- Session par défaut: kernel Symfony via BrowserKit (pas de serveur externe).
- Pour la DB en scénarios: utilisez `APP_ENV=test`, base dédiée, purge entre scénarios, fixtures côté Contexts. Ne dépendez pas du hub Mercure; validez l’état via HTTP/DOM.

6) Structure (aperçu)
assets/               JS/CSS (AssetMapper)
bin/
config/
doc/                  Spécifications (voir spec.md)
features/             Scénarios Behat
public/
src/                  Code applicatif (Entities, Repositories, Services, Controllers)
templates/            Twig (admin/public)
tests/                PHPUnit & Behat Contexts

7) Conventions & règles métier (résumé)
- PSR-12 et bonnes pratiques Symfony. Contrôleurs fins, logique métier dans des services testables.
- Doctrine: entités/repos sous `src/Entity` et `src/Repository`, schéma via migrations.
- Mercure: topics stables (ex: /events/{id}/draws, /events/{id}/public), CORS aligné sur l’URL de l’app, tolérance aux replays/décoche; ne pas supprimer l’historique.
- Messenger: transport Doctrine (`auto_setup=0`). Si async, créer les tables et lancer un worker `php bin/console messenger:consume -vv`.
- Base de données: transactions explicites autour des tirages/validation gagnants, pas de hard-delete (audit par timestamps).
- Règles gagnants: LINE (≥ 1 ligne), DOUBLE_LINE (≥ 2 lignes), FULL_CARD (3 lignes). Gagnants « potentiels » jusqu’à validation admin. Support OFFLINE avec référence libre.
- Séquence: passer au jeu suivant bascule les statuts (courant -> FINISHED, suivant -> RUNNING); démarque = reset manuel des tirages du jeu.

8) Commandes utiles
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate -n
php bin/console cache:clear
php bin/console debug:container
php bin/console debug:router

9) Dépannage rapide
- Mercure ne reçoit pas d’événements: vérifier que `MERCURE_JWT_SECRET` = clés hub et la config CORS/URL publique.
- Erreurs DB en tests: base de test dédiée migrée ou SQLite.
- Actifs/front: AssetMapper actif; `assets:install` est géré par Flex lors de l’installation.

10) Liens
- Spécification: doc/spec.md
- Symfony: https://symfony.com/
- Mercure: https://mercure.rocks/
- Mailpit: https://github.com/axllent/mailpit
