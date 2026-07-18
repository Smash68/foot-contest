.DEFAULT_GOAL := help

.PHONY: help up down shell test test-coverage stan cs-check cs-fix migrate serve

help: ## Liste les commandes disponibles
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Démarre l'environnement (app + database)
	docker compose up -d app

down: ## Arrête l'environnement
	docker compose down

shell: ## Ouvre un shell dans le container app
	docker compose exec app sh

test: ## Lance la suite de tests
	docker compose exec app vendor/bin/phpunit

test-coverage: ## Lance les tests avec couverture (Xdebug)
	docker compose exec app composer test:coverage

stan: ## Analyse statique (PHPStan)
	docker compose exec app composer stan

cs-check: ## Vérifie le style de code (PHP-CS-Fixer, sans modifier)
	docker compose exec app composer cs-check

cs-fix: ## Corrige le style de code (PHP-CS-Fixer)
	docker compose exec app composer cs-fix

migrate: ## Applique les migrations Doctrine (env dev)
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

serve: ## Sert l'app en HTTP à la demande (http://localhost:8000)
	docker compose exec -d app php -S 0.0.0.0:8000 -t public
