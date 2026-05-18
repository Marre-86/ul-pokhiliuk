# === Команды для управления Docker-окружением (запускаются снаружи) ===
docker-setup:
	cp .env.example .env
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --seed
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

lint:
	composer exec --verbose phpcs -- --standard=PSR12 app routes

phpstan:
	vendor/bin/phpstan analyse --memory-limit=2G

test:
	php artisan test --coverage --min=80

check: lint phpstan test