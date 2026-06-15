<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class HealthPaths
{
    #[OA\Get(
        path: '/health',
        operationId: 'healthCheck',
        summary: 'Проверка всех сервисов',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'Все сервисы healthy'),
            new OA\Response(response: 503, description: 'Один или более сервисов unhealthy'),
        ]
    )]
    public function health(): void {}

    #[OA\Get(
        path: '/health/database',
        operationId: 'healthDatabase',
        summary: 'Проверка PostgreSQL',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'Healthy'),
            new OA\Response(response: 503, description: 'Unhealthy'),
        ]
    )]
    public function healthDatabase(): void {}

    #[OA\Get(
        path: '/health/redis',
        operationId: 'healthRedis',
        summary: 'Проверка Redis',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'Healthy'),
            new OA\Response(response: 503, description: 'Unhealthy'),
        ]
    )]
    public function healthRedis(): void {}

    #[OA\Get(
        path: '/health/rabbitmq',
        operationId: 'healthRabbitmq',
        summary: 'Проверка RabbitMQ',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'Healthy'),
            new OA\Response(response: 503, description: 'Unhealthy'),
        ]
    )]
    public function healthRabbitmq(): void {}
}
