# Notification Service

Микросервис массовых уведомлений (SMS, Email) с приоритизацией и гарантией доставки.

## Развёртывание

### Требования

- Docker и Docker Compose
- Git

### Установка

1. Клонировать репозиторий:

```bash
git clone <repository-url> notification-service
cd notification-service
```

2. Настроить окружение:

```bash
cp .env.example .env
```

3. Запустить все сервисы:

```bash
docker-compose up -d --build
```

4. Установить зависимости и сгенерировать ключ приложения:

```bash
docker-compose exec app composer install --no-interaction
docker-compose exec app php artisan key:generate
```

5. Дождаться готовности:

```bash
docker-compose ps
```

Контейнеры postgres, redis и rabbitmq должны быть в статусе `healthy`.

6. Запустить миграции:

```bash
docker-compose exec app php artisan migrate
```

7. Сгенерировать OpenAPI документацию и открыть Swagger UI:

```bash
docker-compose exec app php artisan l5-swagger:generate
```

Swagger UI: http://localhost:8081/api/docs

## API

### Массовая рассылка

```bash
curl -X POST http://localhost:8081/api/v1/notifications/bulk \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "sms",
    "message": "Ваш код: 123456",
    "recipients": ["+79991234567"],
    "priority": "critical",
    "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
  }'
```

### Статусы доставки

- `queued` — принято в очередь
- `sent` — передано провайдеру
- `delivered` — доставлено
- `dropped` — отброшено (ошибка доставки)

```bash
# Статус получателя
curl http://localhost:8081/api/v1/notifications/{id}/recipients/{phone}

# История получателя
curl http://localhost:8081/api/v1/recipients/{phone}/history
```

### Health check

```bash
curl http://localhost:8081/api/health
```

## Доступ к сервисам

| Сервис | URL |
|--------|-----|
| API | http://localhost:8081 |
| Swagger | http://localhost:8081/api/docs |
| RabbitMQ UI | http://localhost:15672 (guest/guest) |

## Переменные окружения

Основные настройки в файле `.env`:

```env
APP_NAME=NotificationService
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8081

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=notification_db
DB_USERNAME=postgres
DB_PASSWORD=postgres

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_MANAGEMENT_PORT=15672
QUEUE_CONNECTION=rabbitmq
```

Генерация ключа приложения (если не выполнена на шаге 4):

```bash
docker-compose exec app php artisan key:generate
```

## Масштабирование

Запуск нескольких worker для обработки очередей:

```bash
docker-compose up -d --scale worker=3
```

## Логи

Просмотр логов:

```bash
# Приложение
docker-compose logs -f app

# Worker
docker-compose logs -f worker

# RabbitMQ
docker-compose logs -f rabbitmq
```

## Подключение к базам данных

PostgreSQL:

```bash
docker-compose exec postgres psql -U postgres -d notification_db
```

Redis:

```bash
docker-compose exec redis redis-cli
```

## Тесты

```bash
docker-compose exec app php artisan test
```

### Полная проверка соответствия ТЗ

```bash
docker-compose exec app bash scripts/verify-all.sh
```

Включает: Pint, 49+ тестов, RabbitMQ E2E, tinker-checks, генерацию Swagger.

### RabbitMQ E2E (отдельно)

```bash
docker-compose exec -e RABBITMQ_INTEGRATION_TESTS=true app php artisan test --filter=RabbitMqIntegrationTest
```

## Соответствие ТЗ

| Требование | Реализация |
|------------|------------|
| Массовая рассылка SMS/Email | `POST /api/v1/notifications/bulk` |
| Приоритизация critical → default → marketing | RabbitMQ очереди + worker |
| Статусы queued / sent / delivered / dropped | Enum + API + двухэтапная доставка |
| RabbitMQ + retry + at-least-once | `queue:work`, `$tries=3`, job idempotency |
| Idempotency | Redis `tryReserve` + unique key в БД |
| Rate limit | Marketing + critical из config |
| Circuit breaker | CLOSED/OPEN/HALF_OPEN + метрики |
| Health + Metrics + Events | `/api/health`, `/api/metrics`, domain events |
| Docker + Swagger | `docker-compose up`, `/api/docs` |
| Интеграционные тесты | sync + RabbitMQ E2E |

## Провайдеры уведомлений

```env
NOTIFICATION_SMS_DRIVER=mock   # mock (симуляция сбоев) | log (production-ready)
NOTIFICATION_EMAIL_DRIVER=mock
```

`log` — детерминированный провайдер с записью в `storage/logs/notifications.log`.

## Качество кода

```bash
composer format        # Laravel Pint
composer format:check  # проверка стиля
```

- PHP 8.5 + `declare(strict_types=1)`
- Laravel 13 conventions
- Единый формат API-ошибок через `ApiResponse`
