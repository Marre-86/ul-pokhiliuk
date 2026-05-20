# === Команды для управления Docker-окружением (запускаются снаружи) ===
docker-setup:
	cp .env.example .env
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --seed
	docker compose exec app php artisan app:generate-open-api

	@echo "✅ Проект успешно развёрнут!"

docker-start:
	docker compose up -d
	@echo "🚀 Контейнеры запущены"

docker-stop:
	docker compose down
	@echo "🛑 Контейнеры остановлены"

# === Команды для работы внутри контейнера (запускаются внутри) ===
go:
	php artisan serve

install:
	composer install

validate:
	composer validate

update-swagger:
	php artisan app:generate-open-api

lint:
	composer exec --verbose phpcs -- app routes

phpstan:
	vendor/bin/phpstan analyse --memory-limit=2G

test:
	php artisan test --coverage --min=74

check: lint phpstan test