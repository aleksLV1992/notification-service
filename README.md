# Notification Service

Сервис массовой отправки SMS и Email с приоритетными очередями.

## Запуск

Требуется Docker и Docker Compose.

```bash
git clone https://github.com/aleksLV1992/notification-service.git
cd notification-service
cp .env.example .env
docker-compose up -d --build
docker-compose exec app composer install --no-interaction
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan l5-swagger:generate
```

Проверить, что `postgres`, `redis` и `rabbitmq` в статусе `healthy`:

```bash
docker-compose ps
```

## API

Массовая рассылка:

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

Статусы: `queued`, `sent`, `delivered`, `dropped`.

```bash
curl http://localhost:8081/api/v1/notifications/{id}/recipients/{phone}
curl http://localhost:8081/api/v1/recipients/{phone}/history
curl http://localhost:8081/api/health
```

## Сервисы

| Сервис | URL |
|--------|-----|
| API | http://localhost:8081 |
| Swagger | http://localhost:8081/api/docs |
| RabbitMQ UI | http://localhost:15672 (guest/guest) |

## Окружение

Основные переменные в `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=notification_db
DB_USERNAME=postgres
DB_PASSWORD=postgres

REDIS_HOST=redis
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq

NOTIFICATION_SMS_DRIVER=mock
NOTIFICATION_EMAIL_DRIVER=mock
```

`mock` — симуляция провайдера, `log` — запись в `storage/logs/notifications.log`.

## Worker

```bash
docker-compose up -d --scale worker=3
```

## Тесты

```bash
docker-compose exec app php artisan test
```
