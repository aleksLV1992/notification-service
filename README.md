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

4. Дождаться готовности:

```bash
docker-compose ps
```

Все контейнеры должны быть в статусе "healthy".

5. Запустить миграции:

```bash
docker-compose exec app php artisan migrate
```

6. Сгенерировать API документацию:

```bash
docker-compose exec app php artisan l5-swagger:generate
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
```

Генерация ключа приложения:

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
