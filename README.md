<h1 align="center">Тестовое задание для ООО «Умная Логистика» <a href="https://ul.su/" target="_blank"><img src="./public/images/ul-logo.png" alt="Ul Logo" width="100"></a></h1>
<h3 align="center">Кандидат: Похилюк Артем</h3>
<h4 align="center">Вакансия: Backend-разработчик (middle)</h4>


<p align="center">
  <a href="https://hh.ru/vacancy/132849981" target="_blank">
    <img src="https://img.shields.io/badge/вакансия-007bff?style=for-the-badge" alt="Вакансия">
  </a>
  &nbsp;
  <a href="https://hh.ru/resume/9207f557ff0bf4ecfc0039ed1f71464d546442" target="_blank">
    <img src="https://img.shields.io/badge/резюме_кандидата-28a745?style=for-the-badge" alt="Резюме кандидата">
  </a>
</p>

## Описание задания

Необходимо реализовать каркас микросервиса уведомлений на Laravel. Сервис должен предоставлять API для
запуска массовой отправки SMS или email-сообщений (без реальной отправки - использовать заглушки). Полный текст ТЗ [здесь](task_description.pdf)
.

<!-- ## Решение

При проектировании сервиса уведомлений в качестве базового был выбран стандартный паттерн <a href="https://refactoring.guru/ru/design-patterns/strategy" target="_blank">"Стратегия"</a>, позволяющий легко расширять сервис уведомлений новыми каналами без изменений существующего кода. Необходимо лишь добавить новый канал (или "стратегию" ) в качестве нового класса в [App\Notifications\Strategies](app/Notifications/Strategies/) и добавить строчку с этим новым классом в мэппинг в [config/notifications.php](config/notifications.php), а новый канал - в Enum [App\Enums\NotificationChannel](app/Enums/NotificationChannel.php).

Каждая стратегия имплементирует интерфейс [App\Contracts\NotificationStrategy](app/Contracts/NotificationStrategy.php) и реализует обязательный метод send() - реальной реализации нет, согласно ТЗ, поставлена заглушка. Заглушка представляет из себя симуляцию обращения во внешний сервис (отправки уведомления) и рандомно возвращающую "успех" или "неудачу" (вероятности этого, а также другие настройки данной заглушки можно подкрутить в config/notifications.php).

[App\Notifications\NotificationService](app/Notifications/NotificationService.php) - сервис уведомлений, в котором можно установить выбранную стратегию и осуществить отправку.

Написана тестовая консольная команда [App\Console\Commands\TestNotificationCommand](app/Console/Commands/TestNotificationCommand.php), демонстрирующая практическое использование сервиса уведомлений. Команда выполняет создание и отправку уведомления. Создание уведомления (но не отправку) также можно сделать путем обращения извне на API-route **POST /api/store-notification**. Чтобы избежать дублирования, переиспользуемый код вынесен в отдельный сервис [App\Services\NotificationCreator](app/Services/NotificationCreator.php).

Реализовано также ещё два эндпойнта API:
- **GET /notification-status/:id** - проверка статуса оповещения по id
- **GET /user/:id/notifications** - возвращает список всех оповещений указанного пользователя. Есть фильтрация по статусу и каналу. -->

### Тесты, статический анализ, code style
- Code style проверяется командой `make lint` - в качестве линтера выбран [PHP_CodeSniffer](https://github.com/squizlabs/php_codesniffer).
- Статический анализ кода PHPSTan проверяется командой `make phpstan`.
- Автотесты написаны, запускаются командой `make test`, покрывают основной бизнес-функционал.
- Возможен запуск вышеперечисленных инструментов одной командой - `make check`.


## Запуск проекта через Docker

Необходимо наличие предустановленного Docker и Docker Compose.

1. **Первичная установка (выполняется один раз)**:
   ```bash
   git clone https://github.com/Marre-86/ul-pokhiliuk.git ul-pokhiliuk
   cd ul-pokhiliuk
   make docker-setup
   ```
   Команда `make docker-setup`:
   - создаст файл .env на основе шаблона;
   - запустит контейнеры в фоновом режиме;
   - установит зависимости PHP (`composer install`);
   - сгенерирует ключ приложения (`php artisan key:generate`);
   - выполнит миграции и наполнит БД тестовыми данными (`php artisan migrate --seed`).

3. **Повторный запуск проекта**:
   ```bash
   make docker-start
   ```
   или
   ```bash
   docker compose up -d
   ```

3. **Приложение будет доступно** по адресу: http://localhost

### Описание сервисов

Docker Compose включает следующие сервисы:

- **app**: PHP-FPM 8.3 с Xdebug для покрытия кода
- **web**: Nginx веб-сервер
- **database**: PostgreSQL 16
- **rabbitmq**: RabbitMQ 4.3
- **redis**: Redis 8.6

### Полезные команды

- **Запуск контейнеров**: `docker compose up -d`
- **Просмотр состояние контейнеров**: `docker compose ps`
- **Просмотр логов**: `docker compose logs -f`
- **Остановка контейнеров**: `docker compose down`
- **Подключение к консоли БД**:
```bash
   docker compose exec database bash
   psql -U laravel -W -d ul_pokhiliuk
   ```
- **Веб-панель RabbitMQ**: http://localhost:15672 (логин/пароль: guest/guest)
- **Подключение к консоли Redis**: `docker exec -it redis redis-cli`
- **Подключение к консоли приложения**: `docker compose exec app bash`

изнутри консоли приложения:
- **Запуск тестов**: `make test`
- **Проверка PHPStan**: `make phpstan`
- **Проверка code style**: `make lint`
- **Линтер, PHPStan, тесты одной командой**: `make check`
