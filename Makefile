# Raccourcis de développement (tout passe par Docker : PHP n'est pas requis sur l'hôte)
DC  = docker compose
PHP = $(DC) run --rm php

.PHONY: up down sh migrate migrate-test test phpstan qa collecte

up: ## Démarre PostgreSQL
	$(DC) up -d postgres

down:
	$(DC) down

sh: ## Shell dans le conteneur PHP
	$(PHP) sh

migrate: ## Applique les migrations (base de dev)
	$(PHP) bin/console doctrine:migrations:migrate -n

migrate-test: ## Applique les migrations sur la base de test
	$(PHP) bin/console doctrine:migrations:migrate -n --env=test

test: migrate-test ## Lance PHPUnit
	$(PHP) vendor/bin/phpunit

phpstan: ## Analyse statique (niveau 8)
	$(PHP) sh -c 'bin/console cache:warmup -q && vendor/bin/phpstan analyse --no-progress --memory-limit=1G'

qa: test phpstan

collecte: ## Lance la collecte (make collecte SOURCE=anti_crise_catalogue ARGS=--dry-run)
	$(PHP) bin/console app:collecte $(SOURCE) $(ARGS)
