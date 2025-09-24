.PHONY: help build up down restart logs shell migrate test clean

# Cores para output
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
NC := \033[0m # No Color

help: ## Mostrar ajuda
	@echo "${GREEN}DubPay - Comandos disponíveis:${NC}"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "${YELLOW}%-20s${NC} %s\n", $$1, $$2}'

build: ## Build dos containers
	@echo "${GREEN}Building containers...${NC}"
	docker-compose build --no-cache

up: ## Iniciar containers
	@echo "${GREEN}Starting containers...${NC}"
	docker-compose up -d
	@echo "${GREEN}Containers started! API available at http://localhost:8000${NC}"

down: ## Parar containers
	@echo "${YELLOW}Stopping containers...${NC}"
	docker-compose down

restart: down up ## Reiniciar containers

logs: ## Ver logs dos containers
	docker-compose logs -f

logs-app: ## Ver logs apenas da aplicação
	docker-compose logs -f app

logs-db: ## Ver logs apenas do banco
	docker-compose logs -f postgres

shell: ## Acessar shell do container da aplicação
	docker-compose exec app sh

db-shell: ## Acessar PostgreSQL
	docker-compose exec postgres psql -U dubpay_user -d dubpay

migrate: ## Executar migrações
	@echo "${GREEN}Running migrations...${NC}"
	docker-compose exec app php artisan migrate

migrate-fresh: ## Executar migrações do zero
	@echo "${YELLOW}Running fresh migrations...${NC}"
	docker-compose exec app php artisan migrate:fresh

seed: ## Executar seeders
	docker-compose exec app php artisan db:seed

tinker: ## Acessar Laravel Tinker
	docker-compose exec app php artisan tinker

test: ## Executar testes
	@echo "${GREEN}Running tests...${NC}"
	docker-compose exec app php artisan test

test-coverage: ## Executar testes com coverage
	docker-compose exec app php artisan test --coverage

clean: ## Limpar containers e volumes
	@echo "${RED}Cleaning up containers and volumes...${NC}"
	docker-compose down -v --remove-orphans
	docker system prune -f

install: build up migrate ## Instalação completa
	@echo "${GREEN}Installation complete!${NC}"
	@echo "${GREEN}API available at: http://localhost:8000${NC}"
	@echo "${GREEN}PostgreSQL available at: localhost:5432${NC}"
	@echo "${GREEN}Redis available at: localhost:6379${NC}"

status: ## Ver status dos containers
	docker-compose ps

# Comandos para desenvolvimento
dev-setup: ## Setup completo para desenvolvimento
	cp .env.example .env
	$(MAKE) install

# Backup e restore
backup-db: ## Backup do banco
	@echo "${GREEN}Creating database backup...${NC}"
	docker-compose exec postgres pg_dump -U dubpay_user dubpay > backup_$(shell date +%Y%m%d_%H%M%S).sql

restore-db: ## Restore do banco (use: make restore-db FILE=backup.sql)
	@echo "${YELLOW}Restoring database from $(FILE)...${NC}"
	docker-compose exec -T postgres psql -U dubpay_user dubpay < $(FILE)

# Comandos para produção
prod-build: ## Build para produção
	docker-compose -f docker-compose.prod.yml build

prod-up: ## Iniciar em produção
	docker-compose -f docker-compose.prod.yml up -d

prod-down: ## Parar produção
	docker-compose -f docker-compose.prod.yml down