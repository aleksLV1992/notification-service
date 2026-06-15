<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Service Configuration
    |--------------------------------------------------------------------------
    */

    // Circuit Breaker настройки
    'circuit_breaker' => [
        'failure_threshold' => env('NOTIFICATION_CIRCUIT_FAILURE_THRESHOLD', 5),
        'success_threshold' => env('NOTIFICATION_CIRCUIT_SUCCESS_THRESHOLD', 2),
        'reset_timeout' => env('NOTIFICATION_CIRCUIT_RESET_TIMEOUT', 30), // секунды
    ],

    // Rate Limiter настройки
    'rate_limiter' => [
        'marketing_limit' => env('NOTIFICATION_MARKETING_LIMIT', 100), // сообщений в час
        'marketing_window' => env('NOTIFICATION_MARKETING_WINDOW', 3600), // секунд
    ],

    // Deduplication настройки
    'deduplication' => [
        'ttl' => env('NOTIFICATION_DEDUPLICATION_TTL', 3600), // секунд
    ],

    // Очереди по приоритетам
    'queues' => [
        'critical' => 'critical',
        'normal' => 'default',
        'marketing' => 'marketing',
    ],

    // Каналы уведомлений
    'channels' => [
        'sms' => 'sms',
        'email' => 'email',
    ],
];
