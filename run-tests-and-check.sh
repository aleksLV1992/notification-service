#!/bin/bash

# Скрипт для запуска тестов и проверки API
# Использование: ./run-tests-and-check.sh

set -e

echo "========================================="
echo "Notification Service - Тестирование"
echo "========================================="
echo ""

# Проверка статуса контейнеров
echo "1. Проверка статуса контейнеров..."
docker-compose ps
echo ""

# Ожидание готовности сервисов
echo "2. Ожидание готовности сервисов (30 сек)..."
sleep 30
echo ""

# Проверка health endpoints
echo "3. Проверка health endpoints..."
echo ""

echo "   - Health check..."
curl -s http://localhost:8081/api/health | head -c 200
echo ""
echo ""

echo "   - Database health..."
curl -s http://localhost:8081/api/health/database | head -c 200
echo ""
echo ""

echo "   - Redis health..."
curl -s http://localhost:8081/api/health/redis | head -c 200
echo ""
echo ""

echo "   - RabbitMQ health..."
curl -s http://localhost:8081/api/health/rabbitmq | head -c 200
echo ""
echo ""

# Запуск тестов
echo "4. Запуск тестов..."
docker-compose exec -T app php artisan test --colors=always
echo ""

# Проверка Swagger
echo "5. Проверка Swagger документации..."
SWAGGER_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8081/api/docs)
if [ "$SWAGGER_STATUS" = "200" ]; then
    echo "   ✅ Swagger доступен: http://localhost:8081/api/docs"
else
    echo "   ❌ Swagger недоступен (статус: $SWAGGER_STATUS)"
fi
echo ""

echo "========================================="
echo "Тестирование завершено!"
echo "========================================="
echo ""
echo "Полезные команды:"
echo "  - Просмотр логов: docker-compose logs -f"
echo "  - Логи app:       docker-compose logs -f app"
echo "  - Логи worker:    docker-compose logs -f worker"
echo "  - Остановить:     docker-compose down"
echo "  - Перезапустить:  docker-compose restart"
echo ""
echo "API Endpoints:"
echo "  - Swagger:        http://localhost:8081/api/docs"
echo "  - Health:         http://localhost:8081/api/health"
echo "  - Metrics:        http://localhost:8081/api/metrics"
echo "  - RabbitMQ UI:    http://localhost:15672 (guest/guest)"
echo "  - PostgreSQL:     localhost:5433 (postgres/postgres)"
echo ""
