#!/usr/bin/env bash
set -euo pipefail

echo "=== Notification Service — полная проверка ТЗ ==="

echo "[1/5] Code style (Pint)"
./vendor/bin/pint --test

echo "[2/5] Unit + Feature tests"
php artisan test

echo "[3/5] RabbitMQ E2E test"
RABBITMQ_INTEGRATION_TESTS=true php artisan test --filter=RabbitMqIntegrationTest

echo "[4/5] Tinker business checks"
php scripts/tinker-checks.php

echo "[5/5] OpenAPI generation"
php artisan l5-swagger:generate

echo ""
echo "=== Все проверки пройдены ==="
