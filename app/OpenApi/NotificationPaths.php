<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class NotificationPaths
{
    #[OA\Post(
        path: '/v1/notifications/bulk',
        operationId: 'bulkNotifications',
        summary: 'Массовая рассылка уведомлений',
        description: 'Запуск массовой отправки SMS или Email. Поддерживает idempotency_key для защиты от дубликатов.',
        tags: ['Notifications'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'message', 'recipients'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email'], example: 'sms'),
                    new OA\Property(property: 'message', type: 'string', example: 'Ваш код подтверждения: 123456'),
                    new OA\Property(
                        property: 'recipients',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['+79991234567', '+79997654321']
                    ),
                    new OA\Property(property: 'priority', type: 'string', enum: ['critical', 'normal', 'marketing'], example: 'critical'),
                    new OA\Property(property: 'idempotency_key', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                    new OA\Property(property: 'metadata', type: 'object', example: ['campaign_id' => '123']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Рассылка создана',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'channel', type: 'string'),
                            new OA\Property(property: 'priority', type: 'string'),
                            new OA\Property(property: 'message', type: 'string'),
                        ], type: 'object'),
                        new OA\Property(property: 'meta', properties: [
                            new OA\Property(property: 'status', type: 'string', example: 'created'),
                        ], type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
            new OA\Response(
                response: 409,
                description: 'Дубликат запроса (idempotency)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'notification_id', type: 'string', format: 'uuid', nullable: true),
                        new OA\Property(property: 'meta', properties: [
                            new OA\Property(property: 'status', type: 'string', enum: ['duplicate', 'processing']),
                        ], type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 429, description: 'Превышен rate limit (marketing или critical)'),
        ]
    )]
    public function bulk(): void {}

    #[OA\Get(
        path: '/v1/notifications/{id}',
        operationId: 'getNotification',
        summary: 'Получить уведомление по ID',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Информация об уведомлении',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'idempotency_key', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
                            new OA\Property(property: 'message', type: 'string'),
                            new OA\Property(property: 'batch_id', type: 'string', format: 'uuid', nullable: true),
                            new OA\Property(property: 'priority', type: 'string', enum: ['critical', 'normal', 'marketing']),
                            new OA\Property(property: 'metadata', type: 'object', nullable: true),
                            new OA\Property(property: 'recipients_count', type: 'integer', nullable: true),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                        ], type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Не найдено'),
        ]
    )]
    public function show(): void {}

    #[OA\Get(
        path: '/v1/notifications/{notificationId}/recipients/{recipientIdentifier}',
        operationId: 'getRecipientStatus',
        summary: 'Статус доставки для получателя',
        description: 'Текущий статус: queued, sent, delivered, dropped',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'notificationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'recipientIdentifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: '+79991234567')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Статус получателя',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'notification_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'recipient_identifier', type: 'string'),
                        new OA\Property(property: 'channel', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'priority', type: 'string'),
                        new OA\Property(property: 'status', type: 'string', enum: ['queued', 'sent', 'delivered', 'dropped']),
                        new OA\Property(property: 'error_message', type: 'string', nullable: true),
                        new OA\Property(property: 'attempts', type: 'integer'),
                        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'failed_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Не найдено'),
        ]
    )]
    public function recipientStatus(): void {}

    #[OA\Get(
        path: '/v1/recipients/{recipientIdentifier}/history',
        operationId: 'getRecipientHistory',
        summary: 'История уведомлений получателя',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'recipientIdentifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'user@example.com')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'История уведомлений',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'last_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ]
                )
            ),
        ]
    )]
    public function recipientHistory(): void {}
}
