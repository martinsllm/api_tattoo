.PHONY: help up down build restart logs shell artisan migrate seed test pint

help: ## Mostra os comandos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ─── Docker ───────────────────────────────────────────────────

up: ## Sobe todos os containers
	docker compose up -d

down: ## Para e remove os containers
	docker compose down

build: ## Reconstrói as imagens
	docker compose build --no-cache

restart: ## Reinicia todos os containers
	docker compose restart

logs: ## Exibe logs em tempo real
	docker compose logs -f

logs-app: ## Exibe logs apenas do app
	docker compose logs -f app

# ─── Setup inicial ────────────────────────────────────────────

setup: ## Configura o ambiente do zero (primeira vez)
	cp .env.docker .env
	docker compose up -d --build
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan storage:link
	docker compose exec app php artisan migrate --seed
	@echo ""
	@echo "✅ Ambiente pronto! Acesse: http://localhost:8000"

# ─── Aplicação ────────────────────────────────────────────────

shell: ## Abre o terminal dentro do container app
	docker compose exec app bash

artisan: ## Roda comando artisan. Ex: make artisan CMD="route:list"
	docker compose exec app php artisan $(CMD)

migrate: ## Roda as migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Recria o banco e roda seeds
	docker compose exec app php artisan migrate:fresh --seed

seed: ## Roda os seeders
	docker compose exec app php artisan db:seed

# ─── Qualidade de código ──────────────────────────────────────

test: ## Roda todos os testes
	docker compose exec app php artisan test --compact

test-filter: ## Roda testes por nome. Ex: make test-filter FILTER=AuthController
	docker compose exec app php artisan test --compact --filter=$(FILTER)

pint: ## Roda o Laravel Pint (formata o código)
	docker compose exec app ./vendor/bin/pint

pint-test: ## Verifica formatação sem aplicar
	docker compose exec app ./vendor/bin/pint --test

# ─── Banco de dados ───────────────────────────────────────────

mysql: ## Abre o client MySQL dentro do container
	docker compose exec mysql mysql -u api_tattoo -psecret api_tattoo

tinker: ## Abre o Tinker
	docker compose exec app php artisan tinker
