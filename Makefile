include .env

DEPLOY_BRANCH ?= dev

up-local:
	@if [ "$(APP_ENV)" != "local" ]; then \
    	echo "❌ Разрешено только для local (сейчас: $(APP_ENV))"; \
    	exit 1; \
    fi
	docker compose up -d

down-local:
	@if [ "$(APP_ENV)" != "local" ]; then \
        echo "❌ Разрешено только для local (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose down

php-local:
	@if [ "$(APP_ENV)" != "local" ]; then \
        echo "❌ Разрешено только для local (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose exec php bash

dev-local:
	@if [ "$(APP_ENV)" != "local" ]; then \
        echo "❌ Разрешено только для local (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose exec node npm run dev

build-local:
	@if [ "$(APP_ENV)" != "local" ]; then \
        echo "❌ Разрешено только для local (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose exec node npm run build

install-local:
	@if [ "$(APP_ENV)" != "local" ]; then \
        echo "❌ Разрешено только для local (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose exec node npm install

up-stage:
	@if [ "$(APP_ENV)" != "staging" ]; then \
        echo "❌ Разрешено только для staging (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose -f docker-compose-stage.yml up -d

down-stage:
	@if [ "$(APP_ENV)" != "staging" ]; then \
        echo "❌ Разрешено только для staging (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose -f docker-compose-stage.yml down

php-stage:
	@if [ "$(APP_ENV)" != "staging" ]; then \
        echo "❌ Разрешено только для staging (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose -f docker-compose-stage.yml exec php bash

reload-env-stage:
	@if [ "$(APP_ENV)" != "staging" ]; then \
        echo "❌ Разрешено только для staging (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	@echo "==> [1/3] config:clear + config:cache (Laravel читает cache/config.php, не .env)..."
	docker compose -f docker-compose-stage.yml exec -T php php artisan config:clear
	docker compose -f docker-compose-stage.yml exec -T php php artisan config:cache
	@echo "==> [2/3] opcache:reset через cachetool (FPM-воркеры держат старый config в памяти)..."
	docker compose -f docker-compose-stage.yml exec -T php cachetool opcache:reset --fcgi=127.0.0.1:9000
	@echo "==> [3/3] horizon:terminate (Horizon — долгоживущий процесс, перечитает config после рестарта supervisor'ом)..."
	docker compose -f docker-compose-stage.yml exec -T php php artisan horizon:terminate
	@echo "✅ .env перечитан: $$(date '+%Y-%m-%d %H:%M:%S')"

deploy-stage:
	@if [ "$(APP_ENV)" != "staging" ]; then \
        echo "❌ Разрешено только для staging (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	@echo "==> [1/6] git pull..."
	git pull origin $(DEPLOY_BRANCH)
	@echo "==> [2/6] docker compose up --build (vendor + sources баkaются в образ)..."
	docker compose -f docker-compose-stage.yml up -d --build --remove-orphans
	@echo "==> [3/6] migrations..."
	docker compose -f docker-compose-stage.yml exec -T php php artisan migrate --force
	@echo "==> [4/6] cache (clear+cache: bootstrap-cache — named volume, переживает рестарт)..."
	docker compose -f docker-compose-stage.yml exec -T php php artisan config:clear
	docker compose -f docker-compose-stage.yml exec -T php php artisan config:cache
	docker compose -f docker-compose-stage.yml exec -T php php artisan route:cache
	docker compose -f docker-compose-stage.yml exec -T php php artisan view:cache
	@echo "==> [5/6] horizon restart..."
	docker compose -f docker-compose-stage.yml exec -T php php artisan horizon:terminate
	@echo "==> [6/6] opcache:reset через cachetool (без рестарта, без downtime)..."
	docker compose -f docker-compose-stage.yml exec -T php cachetool opcache:reset --fcgi=127.0.0.1:9000
	@echo "==> [7/7] health gate: все контейнеры должны быть healthy..."
	@sleep 5
	@unhealthy=$$(docker compose -f docker-compose-stage.yml ps --format '{{.Name}}\t{{.Status}}' | grep -v healthy || true); \
    	if [ -n "$$unhealthy" ]; then \
    		echo "⚠️  Не все контейнеры healthy:"; echo "$$unhealthy"; exit 1; \
    	else \
    		echo "✅ Все контейнеры healthy"; \
    	fi
	@echo "✅ Deploy staging завершён: $$(date '+%Y-%m-%d %H:%M:%S')"

up-prod:
	@if [ "$(APP_ENV)" != "production" ]; then \
        echo "❌ Разрешено только для production (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose -f docker-compose-prod.yml up -d

down-prod:
	@if [ "$(APP_ENV)" != "production" ]; then \
        echo "❌ Разрешено только для production (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose -f docker-compose-prod.yml down

php-prod:
	@if [ "$(APP_ENV)" != "production" ]; then \
        echo "❌ Разрешено только для production (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose -f docker-compose-prod.yml exec php bash

logs-prod:
	@if [ "$(APP_ENV)" != "production" ]; then \
        echo "❌ Разрешено только для production (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	docker compose -f docker-compose-prod.yml logs -f --tail=100

status-prod:
	@if [ "$(APP_ENV)" != "production" ]; then \
        echo "❌ Разрешено только для production (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	@echo "=== Контейнеры ==="
	@docker compose -f docker-compose-prod.yml ps
	@echo ""
	@echo "=== Healthcheck ==="
	@docker compose -f docker-compose-prod.yml ps --format '{{.Name}}\t{{.Status}}'

reload-env-prod:
	@if [ "$(APP_ENV)" != "production" ]; then \
        echo "❌ Разрешено только для production (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	@echo "==> [1/3] config:clear + config:cache (Laravel читает cache/config.php, не .env)..."
	docker compose -f docker-compose-prod.yml exec -T php php artisan config:clear
	docker compose -f docker-compose-prod.yml exec -T php php artisan config:cache
	@echo "==> [2/3] opcache:reset через cachetool (FPM-воркеры держат старый config в памяти)..."
	docker compose -f docker-compose-prod.yml exec -T php cachetool opcache:reset --fcgi=127.0.0.1:9000
	@echo "==> [3/3] horizon:terminate (Horizon — долгоживущий процесс, перечитает config после рестарта supervisor'ом)..."
	docker compose -f docker-compose-prod.yml exec -T php php artisan horizon:terminate
	@echo "✅ .env перечитан: $$(date '+%Y-%m-%d %H:%M:%S')"

deploy-prod:
	@if [ "$(APP_ENV)" != "production" ]; then \
        echo "❌ Разрешено только для production (сейчас: $(APP_ENV))"; \
        exit 1; \
    fi
	@echo "==> [1/9] git pull..."
	git pull origin main
	@echo "==> [2/9] docker compose up --build..."
	docker compose -f docker-compose-prod.yml up -d --build --remove-orphans
	@echo "==> [3/9] ожидание healthcheck..."
	@docker compose -f docker-compose-prod.yml exec -T php timeout 60 sh -c 'until SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong; do sleep 2; done' || (echo "❌ PHP healthcheck не прошёл"; exit 1)
	@echo "==> [4/9] composer install..."
	docker compose -f docker-compose-prod.yml exec -T php composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
	@echo "==> [5/9] migrations..."
	docker compose -f docker-compose-prod.yml exec -T php php artisan migrate --force
	@echo "==> [6/9] cache..."
	docker compose -f docker-compose-prod.yml exec -T php php artisan config:cache
	docker compose -f docker-compose-prod.yml exec -T php php artisan route:cache
	docker compose -f docker-compose-prod.yml exec -T php php artisan view:cache
	docker compose -f docker-compose-prod.yml exec -T php php artisan event:cache
	@echo "==> [7/9] horizon restart..."
	docker compose -f docker-compose-prod.yml exec -T php php artisan horizon:terminate
	@echo "==> [8/9] opcache:reset через cachetool (без рестарта, без downtime)..."
	docker compose -f docker-compose-prod.yml exec -T php cachetool opcache:reset --fcgi=127.0.0.1:9000
	@echo "==> [9/9] финальная проверка healthcheck..."
	@sleep 5
	@docker compose -f docker-compose-prod.yml ps --format '{{.Name}}\t{{.Status}}' | grep -v healthy && echo "⚠️  Не все контейнеры healthy — проверьте вручную" || echo "✅ Все контейнеры healthy"
	@echo "✅ Deploy production завершён: $$(date '+%Y-%m-%d %H:%M:%S')"
