# Raccourcis de développement (tout passe par Docker : PHP n'est pas requis sur l'hôte)
DC  = docker compose
PHP = $(DC) run --rm php

.PHONY: up down sh migrate migrate-test test phpstan qa collecte jwt utilisateur api front front-qa

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

qa: test phpstan front-qa

collecte: ## Lance la collecte (make collecte SOURCE=anti_crise_catalogue ARGS=--dry-run)
	$(PHP) bin/console app:collecte $(SOURCE) $(ARGS)

jwt: ## Génère les clés JWT (si absentes)
	$(PHP) bin/console lexik:jwt:generate-keypair --skip-if-exists

utilisateur: ## Crée un compte (make utilisateur EMAIL=moi@exemple.fr ADMIN=--admin)
	$(DC) run --rm -it php bin/console app:utilisateur:creer $(EMAIL) $(ADMIN)

api: ## Démarre PostgreSQL et l'API de développement (http://localhost:8088/api/docs)
	$(DC) up -d postgres api

front: api ## Démarre le front Vite (http://localhost:5180)
	npm --prefix frontend run dev

front-qa: ## Types, lint, format, tests et build du front
	cd frontend && npx tsc -b && npm run lint && npm run format:check && npm test && npm run build
