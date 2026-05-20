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

## Решение

**POST /api/notifications/send-bulk** - базовый API-route сервиса, принимающий извне массив данных содержащий канал связи, текст сообщения, приоритет и массив идентификаторов
получателей. Также в принимаемом запросе должен быть указан заголовок *X-Request-ID* (в формате UUID v4) - сервис на его основании осуществит дедубликацию (используется Redis).

После дедубликации осуществляется создание индивидуальных уведомлений в БД и отправка их в очередь (используется RabbitMQ) - в зависимости от установленного в запросе приоритета либо в очередь 'high-priority' либо в 'low-priority'. [App\Jobs\SendNotificationJob](app/Jobs/SendNotificationJob.php) вызывает [App\Notifications\NotificationService](app/Notifications/NotificationService.php) - унифицированный для всех каналов связи сервис, спроектированный в паттерне <a href="https://refactoring.guru/ru/design-patterns/strategy" target="_blank">"Стратегия"</a> и позволяющий легко себя расширять новыми каналами без изменений существующего кода. Необходимо лишь добавить новый канал (или "стратегию" ) в качестве нового класса в [App\Notifications\Strategies](app/Notifications/Strategies/) и добавить строчку с этим новым классом в мэппинг в [config/notifications.php](config/notifications.php), а новый канал - в Enum [App\Enums\NotificationChannel](app/Enums/NotificationChannel.php).

Каждая стратегия имплементирует интерфейс [App\Contracts\NotificationStrategy](app/Contracts/NotificationStrategy.php) и реализует обязательный метод send() - реальной реализации нет, согласно ТЗ, поставлена заглушка. Заглушка представляет из себя имитацию обращения во внешний сервис (отправки уведомления) и рандомно возвращающую "успех" или "неудачу" (вероятности этого, а также другие настройки данной заглушки можно подкрутить в config/notifications.php).

В качестве Retry-механизмов отправки используются <a href="https://laravel.com/docs/13.x/queues#dealing-with-failed-jobs" target="_blank">стандартные инструменты Laravel по работе с очередями</a>: в SendNotificationJob мы можем выставить значения кол-ва попыток отправки и их временные интервалы. В случае если все попытки будут неудачными, уведомление получит статус 'FAILED' (timestamp, кол-во попыток и сообщение об ошибке будут записаны в БД).

Таким образом, комбинация дедубликации Redis и настроенных Retry-механизмов Laravel обеспечивают гарантию отправки уведомления на уровне превышающем ***at‑least‑once***.

Для фиксации доставки/неудачи доставки ранее отправленного уведомления предназначен эндпойнт **POST /api/notifications/webhook/delivery** (вебхук). Все запросы к данному вебхуку должны содержать заголовок аутентификации:

```http
Authorization: Bearer test-token-def456
```

Таким образом, внешний провайдер может обратиться на данный вебхук и прислать информацию о доставке или неудаче доставки какого-то уведомления и тогда в БД статус данного уведомления изменится либо на 'DELIVERED' либо на 'DELIVERY_FAILED' (timestamp и сообщение об ошибке тоже запишутся).


<!-- Реализовано также ещё два эндпойнта API:
- **GET /notification-status/:id** - проверка статуса оповещения по id
- **GET /user/:id/notifications** - возвращает список всех оповещений указанного пользователя. Есть фильтрация по статусу и каналу. -->

### Тесты, статический анализ, code style
- Code style проверяется командой `make lint` - в качестве линтера выбран [PHP_CodeSniffer](https://github.com/squizlabs/php_codesniffer).
- Статический анализ кода PHPSTan проверяется командой `make phpstan`.
- Автотесты написаны, запускаются командой `make test`, покрывают основной бизнес-функционал.
- Возможен запуск вышеперечисленных инструментов одной командой - `make check`.

На момент сдачи приложения линтер не находит погрешностей, phpstan проходит проверку с уровнем 5, покрытие тестами составляет


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
   - сформирует Swagger-документацию (OpenAPI) (`php artisan migrate --seed`).

3. **Повторный запуск проекта**:
   ```bash
   make docker-start
   ```
   или
   ```bash
   docker compose up -d
   ```

3. **Приложение будет доступно** по адресу: http://localhost

  **Swagger-документация доступна** по адресу: http://localhost/api-docs


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
- **Обновить swagger-документацию**: `make update-swagger`
