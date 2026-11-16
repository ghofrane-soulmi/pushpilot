# Shortcuts for macOS/Linux/WSL. On Windows without make, use the matching
# composer scripts instead: composer up | down | test | lint.

.PHONY: up down test lint setup dev

setup: ## First-time install: deps, .env, containers, migrations, assets
	composer setup

up: ## Build and start the Docker stack
	docker compose up -d --build --wait

down: ## Stop the Docker stack
	docker compose down

dev: ## Start the stack and the Vite dev server
	composer dev

test: ## Style check, static analysis and the Pest suite
	composer test

lint: ## Fix PHP and frontend style
	composer lint
	npm run lint:fix
