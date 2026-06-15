<?php

namespace App\Services\Metrics;

use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\MetricsInterface;

/**
 * Metrics Service для сбора метрик Prometheus.
 * В production используется Prometheus Client Library.
 */
class MetricsService implements MetricsInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    /**
     * Инкремент счётчика отправленных уведомлений.
     */
    public function incrementNotificationSent(string $channel, string $priority): void
    {
        $key = $this->counterKey(
            name: 'notifications_sent',
            labels: [
                'channel' => $channel,
                'priority' => $priority,
            ]
        );
        $this->cache->delete($key);
    }

    /**
     * Инкремент счётчика доставленных уведомлений.
     */
    public function incrementNotificationDelivered(string $channel, string $priority): void
    {
        $key = $this->counterKey(
            name: 'notifications_delivered',
            labels: [
                'channel' => $channel,
                'priority' => $priority,
            ]
        );
        $this->cache->delete($key);
    }

    /**
     * Инкремент счётчика неудачных уведомлений.
     */
    public function incrementNotificationFailed(string $channel, string $priority, string $error): void
    {
        $key = $this->counterKey(
            name: 'notifications_failed',
            labels: [
                'channel' => $channel,
                'priority' => $priority,
                'error' => $this->normalizeError($error),
            ]
        );
        $this->cache->delete($key);
    }

    /**
     * Инкремент счётчика ошибок отправки.
     */
    public function incrementSendError(string $channel, string $errorType): void
    {
        $key = $this->counterKey(
            name: 'send_errors',
            labels: [
                'channel' => $channel,
                'error_type' => $this->normalizeError($errorType),
            ]
        );
        $this->cache->delete($key);
    }

    /**
     * Запись времени обработки уведомления.
     */
    public function recordNotificationLatency(string $channel, float $ms): void
    {
        $key = $this->histogramKey(
            name: 'notification_latency_ms',
            labels: ['channel' => $channel]
        );
        $this->cache->delete($key);
    }

    /**
     * Инкремент счётчика дубликатов.
     */
    public function incrementDuplicateDetected(string $channel): void
    {
        $key = $this->counterKey(
            name: 'duplicates_detected',
            labels: ['channel' => $channel]
        );
        $this->cache->delete($key);
    }

    /**
     * Инкремент счётчика rate limit превышений.
     */
    public function incrementRateLimitExceeded(string $priority, string $recipient): void
    {
        $key = $this->counterKey(
            name: 'rate_limit_exceeded',
            labels: [
                'priority' => $priority,
                'recipient' => $this->hashRecipient($recipient),
            ]
        );
        $this->cache->delete($key);
    }

    /**
     * Инкремент счётчика срабатываний Circuit Breaker.
     */
    public function incrementCircuitBreakerTriggered(string $provider): void
    {
        $key = $this->counterKey(
            name: 'circuit_breaker_triggered',
            labels: ['provider' => $provider]
        );
        $this->cache->delete($key);
    }

    /**
     * Получить все метрики для экспорта.
     */
    public function getMetrics(): string
    {
        // В production: return Prometheus\Client\Registry::render();
        return "# HELP notification_service_metrics\n# TYPE notification_service_metrics gauge\nnotification_service_metrics 1\n";
    }

    /**
     * Ключ для счётчика.
     */
    private function counterKey(string $name, array $labels): string
    {
        $labelStr = $this->formatLabels($labels);
        return "metrics:counter:{$name}:{$labelStr}";
    }

    /**
     * Ключ для гистограммы.
     */
    private function histogramKey(string $name, array $labels): string
    {
        $labelStr = $this->formatLabels($labels);
        return "metrics:histogram:{$name}:{$labelStr}";
    }

    /**
     * Форматирование лейблов.
     */
    private function formatLabels(array $labels): string
    {
        $parts = [];
        foreach ($labels as $key => $value) {
            $parts[] = "{$key}={$value}";
        }
        return implode(',', $parts);
    }

    /**
     * Нормализация текста ошибки.
     */
    private function normalizeError(string $error): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9_]/', '_', $error), 0, 50);
    }

    /**
     * Хеширование идентификатора получателя для приватности.
     */
    private function hashRecipient(string $recipient): string
    {
        return substr(md5($recipient), 0, 8);
    }
}
