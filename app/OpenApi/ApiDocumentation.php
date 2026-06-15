<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Notification Service API',
    description: <<<'DESC'
Микросервис массовых уведомлений (SMS, Email) с приоритизацией и гарантией доставки.

**Статусы доставки:** `queued` → `sent` → `delivered` / `dropped`

**Приоритеты:** `critical` (наивысший), `normal`, `marketing`
DESC
)]
#[OA\Server(url: 'http://localhost:8081/api', description: 'Local Docker')]
#[OA\Tag(name: 'Notifications', description: 'Массовая рассылка и статусы доставки')]
#[OA\Tag(name: 'Health', description: 'Проверка состояния сервисов')]
#[OA\Tag(name: 'Metrics', description: 'Prometheus метрики')]
class ApiDocumentation {}
