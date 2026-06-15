<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class MetricsPaths
{
    #[OA\Get(
        path: '/metrics',
        operationId: 'getMetrics',
        summary: 'Prometheus метрики',
        description: 'Экспорт счётчиков в формате Prometheus text exposition.',
        tags: ['Metrics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Метрики в формате Prometheus',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string', example: "# HELP notifications_sent_total Counter metric\n# TYPE notifications_sent_total counter\nnotifications_sent_total{channel=\"sms\",priority=\"critical\"} 3\n")
                )
            ),
        ]
    )]
    public function metrics(): void {}
}
