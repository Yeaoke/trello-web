.DEFAULT_GOAL := help

ifneq (,$(wildcard .env))
include .env
export
endif

.PHONY: help init build up down ps logs clean setup composer migrate migrate-status migrate-rollback keygen config-show sh psql

help:
	@echo "Доступные команды:"
	@echo "  make init             - создать .env из .env.example и подставить UID/GID текущего пользователя"
	@echo "  make build            - собрать Docker-образы (docker compose build)"
	@echo "  make up               - запустить контейнеры в фоне (docker compose up -d)"
	@echo "  make down             - остановить контейнеры (docker compose down)"
	@echo "  make ps               - показать состояние контейнеров (docker compose ps)"
	@echo "  make logs             - показать логи контейнеров в реальном времени"
	@echo "  make clean            - остановить контейнеры и УДАЛИТЬ данные PostgreSQL (docker compose down -v)"
	@echo "  make setup            - быстрый старт: init + build + up + composer + migrate"
	@echo "  make composer         - установить PHP-зависимости (composer install в контейнере)"
	@echo "  make migrate          - применить миграции базы данных (php bin/migrate up)"
	@echo "  make migrate-status   - показать статус миграций (php bin/migrate status)"
	@echo "  make migrate-rollback - откатить последнюю миграцию (php bin/migrate down)"
	@echo "  make keygen           - сгенерировать APP_KEY (php bin/config keygen)"
	@echo "  make config-show      - показать итоговый конфиг (php bin/config show)"
	@echo "  make sh               - открыть shell в контейнере приложения"
	@echo "  make psql             - подключиться к PostgreSQL (psql)"

init:
	@if [ -f .env ]; then \
		echo ".env уже существует"; \
	else \
		cp .env.example .env; \
		echo ".env создан из .env.example"; \
	fi
	@sed -i.bak -e "s/^UID=.*/UID=$$(id -u)/" -e "s/^GID=.*/GID=$$(id -g)/" .env && rm -f .env.bak
	@echo "UID/GID обновлены: UID=$$(id -u) GID=$$(id -g)"

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

ps:
	docker compose ps

logs:
	docker compose logs -f

clean:
	@echo "Удаление контейнеров и БД (volume pgdata)"
	docker compose down -v

setup: init build up composer migrate
	@echo "Готово: http://localhost:$${APP_PORT:-8080}"

composer:
	docker compose exec -u www-data app composer install

migrate:
	docker compose exec app php bin/migrate up

migrate-status:
	docker compose exec app php bin/migrate status

migrate-rollback:
	docker compose exec app php bin/migrate down

keygen:
	@KEY=$$(docker compose exec -T app php bin/config keygen | head -n1); \
	sed -i.bak -e "s|^APP_KEY=.*|APP_KEY=$$KEY|" .env && rm -f .env.bak; \
	echo "APP_KEY обновлён в .env. Перезапусти app: make up (старые enc:-значения нужно перешифровать)"

config-show:
	docker compose exec app php bin/config show

sh:
	docker compose exec app sh

psql:
	docker compose exec db psql -U $(POSTGRES_USER) -d $(POSTGRES_DB)
