<?php

declare(strict_types=1);

return [
    'circuit_breaker' => [
        'failure_threshold' => env('NOTIFICATION_CIRCUIT_FAILURE_THRESHOLD', 5),
        'success_threshold' => env('NOTIFICATION_CIRCUIT_SUCCESS_THRESHOLD', 2),
        'reset_timeout' => env('NOTIFICATION_CIRCUIT_RESET_TIMEOUT', 30),
    ],

    'rate_limiter' => [
        'marketing_limit' => env('NOTIFICATION_MARKETING_LIMIT', 100),
        'marketing_window' => env('NOTIFICATION_MARKETING_WINDOW', 3600),
        'critical_limit' => env('NOTIFICATION_CRITICAL_LIMIT', 10),
        'critical_window' => env('NOTIFICATION_CRITICAL_WINDOW', 60),
    ],

    'deduplication' => [
        'ttl' => env('NOTIFICATION_DEDUPLICATION_TTL', 3600),
    ],

    'providers' => [
        'sms' => [
            'driver' => env('NOTIFICATION_SMS_DRIVER', 'mock'),
        ],
        'email' => [
            'driver' => env('NOTIFICATION_EMAIL_DRIVER', 'mock'),
        ],
    ],
];
