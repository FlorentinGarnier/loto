.PHONY: help install start stop restart logs db-create db-migrate db-reset test test-unit test-functional cs-check cs-fix clear cache seed docker-up docker-down docker-restart db-diff test-behat-verbose test-db-reset routes services entities console import-cards watch watch-stop

.DEFAULT_GOAL := help

# Colors
COLOR_RESET   = \033[0m
COLOR_INFO    = \033[32m
COLOR_COMMENT = \033[33m
COLOR_ERROR   = \033[31m

## —— 🎯 Loto Quine Makefile ————————————————————————————————————————————————
help: ## Affiche cette aide
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— 🚀 Projet ——————————————————————————————————————————————————————————————
install: ## Installation complète du projet
	@echo "$(COLOR_INFO)Installation des dépendances Composer...$(COLOR_RESET)"
	composer install
	@echo "$(COLOR_INFO)Démarrage de l'infrastructure Docker...$(COLOR_RESET)"
	$(MAKE) docker-up
	@echo "$(COLOR_INFO)Attente du démarrage de PostgreSQL...$(COLOR_RESET)"
	@sleep 3
	@echo "$(COLOR_INFO)Création de la base de données...$(COLOR_RESET)"
	$(MAKE) db-create
	@echo "$(COLOR_INFO)Migration de la base de données...$(COLOR_RESET)"
	$(MAKE) db-migrate
	@echo "$(COLOR_INFO)✅ Installation terminée !$(COLOR_RESET)"
	@echo "$(COLOR_COMMENT)Vous pouvez maintenant lancer : make start$(COLOR_RESET)"

start: ## Démarre le serveur PHP et l'infrastructure
	@echo "$(COLOR_INFO)Démarrage de l'infrastructure Docker...$(COLOR_RESET)"
	$(MAKE) docker-up
	@echo "$(COLOR_INFO)Démarrage du serveur PHP sur http://127.0.0.1:8001...$(COLOR_RESET)"
	php -S 127.0.0.1:8001 -t public

stop: ## Arrête l'infrastructure Docker
	@echo "$(COLOR_INFO)Arrêt de l'infrastructure...$(COLOR_RESET)"
	$(MAKE) docker-down

restart: ## Redémarre l'infrastructure
	$(MAKE) stop
	$(MAKE) docker-up

## —— 🐳 Docker ——————————————————————————————————————————————————————————————
docker-up: ## Démarre les conteneurs Docker (postgres, mercure, mailpit)
	docker compose up -d database mercure mailer

docker-down: ## Arrête les conteneurs Docker
	docker compose down

docker-restart: ## Redémarre les conteneurs Docker
	docker compose restart

logs: ## Affiche les logs Docker
	docker compose logs -f

## —— 🗄️  Base de données ——————————————————————————————————————————————————
db-create: ## Crée la base de données
	symfony php bin/console doctrine:database:create --if-not-exists

db-migrate: ## Applique les migrations
	symfony php bin/console doctrine:migrations:migrate -n

db-reset: ## Reset complet de la base (DROP + CREATE + MIGRATE)
	@echo "$(COLOR_ERROR)⚠️  ATTENTION : Suppression totale de la base de données !$(COLOR_RESET)"
	php bin/console doctrine:database:drop --force --if-exists
	$(MAKE) db-create
	$(MAKE) db-migrate
	@echo "$(COLOR_INFO)✅ Base de données réinitialisée$(COLOR_RESET)"

db-diff: ## Génère une migration à partir des changements d'entités
	symfony php bin/console doctrine:migrations:diff

seed: ## Charge des données de démo
	symfony php bin/console app:seed:demo

## —— 🧪 Tests ———————————————————————————————————————————————————————————————
test: ## Lance tous les tests (PHPSpec + Behat)
	@echo "$(COLOR_INFO)Exécution des tests unitaires (PHPSpec)...$(COLOR_RESET)"
	$(MAKE) test-unit
	@echo "$(COLOR_INFO)Exécution des tests fonctionnels (Behat)...$(COLOR_RESET)"
	$(MAKE) test-functional
	@echo "$(COLOR_INFO)✅ Tous les tests sont passés !$(COLOR_RESET)"

test-unit: ## Lance les tests unitaires (PHPSpec)
	symfony php vendor/bin/phpspec run

test-functional: ## Lance les tests fonctionnels (Behat)
	symfony php vendor/bin/behat --format=progress

test-behat-verbose: ## Lance Behat en mode verbose
	symfony php vendor/bin/behat --format=pretty

test-db-reset: ## Reset la base de test
	symfony php bin/console doctrine:database:drop --force --if-exists --env=test
	symfony php bin/console doctrine:database:create --env=test
	symfony php bin/console doctrine:migrations:migrate -n --env=test

## —— 🎨 Qualité de code ————————————————————————————————————————————————————
cs-check: ## Vérifie le style de code
	symfony vendor/bin/php-cs-fixer fix --dry-run --diff --verbose

cs-fix: ## Corrige le style de code
	symfony vendor/bin/php-cs-fixer fix

## —— 🧹 Cache ———————————————————————————————————————————————————————————————
clear: cache ## Alias de cache

cache: ## Vide le cache Symfony
	symfony php bin/console cache:clear
	symfony php bin/console cache:warmup

## —— 📊 Informations ———————————————————————————————————————————————————————
routes: ## Liste toutes les routes
	symfony php bin/console debug:router

services: ## Liste tous les services
	symfony php bin/console debug:container

entities: ## Liste toutes les entités
	symfony php bin/console doctrine:mapping:info

## —— 🔧 Outils de développement ———————————————————————————————————————————
console: ## Lance la console Symfony interactive
	symfony php bin/console

import-cards: ## Importe des cartons depuis un fichier CSV (ex: make import-cards FILE=cards.csv)
	@test -n "$(FILE)" || (echo "Usage : make import-cards FILE=chemin/vers/cartons.csv" && exit 1)
	symfony php bin/console app:cards:import 0 $(FILE)

watch: ## Lance le serveur avec rechargement auto (nécessite symfony-cli)
	symfony serve --daemon
	@echo "$(COLOR_INFO)Serveur démarré sur https://127.0.0.1:8000$(COLOR_RESET)"

watch-stop: ## Arrête le serveur symfony-cli
	symfony server:stop
