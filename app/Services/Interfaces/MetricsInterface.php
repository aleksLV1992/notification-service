<?php

namespace App\Services\Interfaces;

interface MetricsInterface
{
    /**
     * Инкремент счётчика отправленных уведомлений.
     */
    public function incrementNotificationSent(string $channel, string $priority): void;

    /**
     * Инкремент счётчика дубликатов.
     */
    public function incrementDuplicateDetected(string $channel): void;

    /**
     * Инкремент счётчика превышения rate limit.
     */
    public function incrementRateLimitExceeded(string $priority, string $recipient): void;

    /**
     * Инкремент счётчика ошибок отправки.
     */
    public function incrementSendError(string $channel, string $errorType): void;

    /**
     * Получить метрики для Prometheus.
     */
    public function getMetrics(): string;
}
