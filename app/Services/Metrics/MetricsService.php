<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\MetricsInterface;

class MetricsService implements MetricsInterface
{
    private const REGISTRY_KEY = 'metrics:registry';

    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function incrementNotificationSent(string $channel, string $priority): void
    {
        $this->incrementCounter('notifications_sent_total', [
            'channel' => $channel,
            'priority' => $priority,
        ]);
    }

    public function incrementNotificationDelivered(string $channel, string $priority): void
    {
        $this->incrementCounter('notifications_delivered_total', [
            'channel' => $channel,
            'priority' => $priority,
        ]);
    }

    public function incrementNotificationFailed(string $channel, string $priority, string $error): void
    {
        $this->incrementCounter('notifications_failed_total', [
            'channel' => $channel,
            'priority' => $priority,
            'error' => $this->normalizeError($error),
        ]);
    }

    public function incrementSendError(string $channel, string $errorType): void
    {
        $this->incrementCounter('send_errors_total', [
            'channel' => $channel,
            'error_type' => $this->normalizeError($errorType),
        ]);
    }

    public function recordNotificationLatency(string $channel, float $ms): void
    {
        $key = $this->histogramKey(
            name: 'notification_latency_ms',
            labels: ['channel' => $channel]
        );
        $this->cache->set($key, (string) $ms, 3600);
        $this->registerKey($key);
    }

    public function incrementDuplicateDetected(string $channel): void
    {
        $this->incrementCounter('duplicates_detected_total', ['channel' => $channel]);
    }

    public function incrementRateLimitExceeded(string $priority, string $recipient): void
    {
        $this->incrementCounter('rate_limit_exceeded_total', [
            'priority' => $priority,
            'recipient' => $this->hashRecipient($recipient),
        ]);
    }

    public function incrementCircuitBreakerTriggered(string $provider): void
    {
        $this->incrementCounter('circuit_breaker_triggered_total', ['provider' => $provider]);
    }

    public function getMetrics(): string
    {
        $registry = $this->getRegistry();
        $lines = [];

        foreach ($registry as $key) {
            if (str_starts_with($key, 'metrics:counter:')) {
                $lines = array_merge($lines, $this->renderCounter($key));

                continue;
            }

            if (str_starts_with($key, 'metrics:histogram:')) {
                $lines = array_merge($lines, $this->renderGauge($key));
            }
        }

        return implode("\n", $lines).(count($lines) > 0 ? "\n" : '');
    }

    private function incrementCounter(string $name, array $labels): void
    {
        $key = $this->counterKey($name, $labels);
        $current = (int) ($this->cache->get($key) ?? 0);
        $this->cache->set($key, (string) ($current + 1), 86400);
        $this->registerKey($key);
    }

    private function renderCounter(string $key): array
    {
        $value = (int) ($this->cache->get($key) ?? 0);
        $payload = substr($key, strlen('metrics:counter:'));
        [$name, $labelString] = explode(':', $payload, 2);
        $labels = $this->parseLabels($labelString);

        return [
            "# HELP {$name} Counter metric",
            "# TYPE {$name} counter",
            $this->formatMetricLine($name, $labels, $value),
        ];
    }

    private function renderGauge(string $key): array
    {
        $value = (float) ($this->cache->get($key) ?? 0);
        $payload = substr($key, strlen('metrics:histogram:'));
        [$name, $labelString] = explode(':', $payload, 2);
        $labels = $this->parseLabels($labelString);

        return [
            "# HELP {$name} Gauge metric",
            "# TYPE {$name} gauge",
            $this->formatMetricLine($name, $labels, $value),
        ];
    }

    private function formatMetricLine(string $name, array $labels, int|float $value): string
    {
        if ($labels === []) {
            return "{$name} {$value}";
        }

        $formattedLabels = [];
        foreach ($labels as $label => $labelValue) {
            $formattedLabels[] = "{$label}=\"{$labelValue}\"";
        }

        return $name.'{'.implode(',', $formattedLabels)."} {$value}";
    }

    private function parseLabels(string $labelString): array
    {
        if ($labelString === '') {
            return [];
        }

        $labels = [];
        foreach (explode(',', $labelString) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $labels[$key] = $value;
        }

        return $labels;
    }

    private function registerKey(string $key): void
    {
        $registry = $this->getRegistry();
        if (! in_array($key, $registry, true)) {
            $registry[] = $key;
            $this->cache->set(self::REGISTRY_KEY, json_encode($registry), 86400);
        }
    }

    private function getRegistry(): array
    {
        $registry = $this->cache->get(self::REGISTRY_KEY);

        return is_string($registry) ? json_decode($registry, true) ?? [] : [];
    }

    private function counterKey(string $name, array $labels): string
    {
        return 'metrics:counter:'.$name.':'.$this->formatLabels($labels);
    }

    private function histogramKey(string $name, array $labels): string
    {
        return 'metrics:histogram:'.$name.':'.$this->formatLabels($labels);
    }

    private function formatLabels(array $labels): string
    {
        $parts = [];
        foreach ($labels as $key => $value) {
            $parts[] = "{$key}={$value}";
        }

        return implode(',', $parts);
    }

    private function normalizeError(string $error): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9_]/', '_', $error), 0, 50);
    }

    private function hashRecipient(string $recipient): string
    {
        return substr(md5($recipient), 0, 8);
    }
}
