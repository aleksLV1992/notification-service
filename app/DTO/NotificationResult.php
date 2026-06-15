<?php

namespace App\DTO;

use App\Models\Notification;

class NotificationResult
{
    public function __construct(
        public Notification $notification,
        public bool $isDuplicate = false,
        public bool $isRateLimitExceeded = false,
        public ?string $message = null,
    ) {}

    public static function duplicate(Notification $notification, string $message = 'Duplicate request'): self
    {
        return new self(
            notification: $notification,
            isDuplicate: true,
            isRateLimitExceeded: false,
            message: $message
        );
    }

    public static function rateLimitExceeded(string $message = 'Rate limit exceeded'): self
    {
        $notification = new Notification();
        return new self(
            notification: $notification,
            isDuplicate: false,
            isRateLimitExceeded: true,
            message: $message
        );
    }

    public static function success(Notification $notification): self
    {
        return new self(
            notification: $notification,
            isDuplicate: false,
            isRateLimitExceeded: false
        );
    }
}
