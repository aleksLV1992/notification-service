<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

interface MetricsInterface
{
    public function incrementNotificationSent(string $channel, string $priority): void;

    public function incrementDuplicateDetected(string $channel): void;

    public function incrementRateLimitExceeded(string $priority, string $recipient): void;

    public function incrementSendError(string $channel, string $errorType): void;

    public function incrementNotificationDelivered(string $channel, string $priority): void;

    public function incrementNotificationFailed(string $channel, string $priority, string $error): void;

    public function getMetrics(): string;
}
