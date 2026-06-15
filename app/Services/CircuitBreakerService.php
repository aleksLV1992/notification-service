<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CircuitBreakerOpenException;
use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\MetricsInterface;
use Throwable;

class CircuitBreakerService
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    private int $failureThreshold;

    private int $successThreshold;

    private int $resetTimeout;

    public function __construct(
        private CacheInterface $cache,
        private MetricsInterface $metrics,
    ) {
        $this->failureThreshold = (int) config('notification.circuit_breaker.failure_threshold', 5);
        $this->successThreshold = (int) config('notification.circuit_breaker.success_threshold', 2);
        $this->resetTimeout = (int) config('notification.circuit_breaker.reset_timeout', 30);
    }

    public function call(string $provider, callable $callback): mixed
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset($provider)) {
                $this->setState($provider, self::STATE_HALF_OPEN);
            } else {
                $retryAfter = $this->getRetryAfter($provider);
                $this->metrics->incrementCircuitBreakerTriggered($provider);
                throw new CircuitBreakerOpenException($provider, $retryAfter);
            }
        }

        try {
            $result = $callback();
            $this->onSuccess($provider);

            return $result;
        } catch (Throwable $e) {
            $this->onFailure($provider);
            throw $e;
        }
    }

    public function getState(string $provider): string
    {
        return $this->cache->get($this->stateKey($provider)) ?? self::STATE_CLOSED;
    }

    public function getStats(string $provider): array
    {
        return [
            'state' => $this->getState($provider),
            'failure_count' => (int) $this->cache->get($this->failureCountKey($provider)),
            'success_count' => (int) $this->cache->get($this->successCountKey($provider)),
            'last_failure_time' => (int) $this->cache->get($this->lastFailureTimeKey($provider)),
            'retry_after' => $this->getRetryAfter($provider),
        ];
    }

    private function onSuccess(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = $this->incrementSuccessCount($provider);
            if ($successCount >= $this->successThreshold) {
                $this->setState($provider, self::STATE_CLOSED);
                $this->resetFailureCount($provider);
            }
        } else {
            $this->resetFailureCount($provider);
        }
    }

    private function onFailure(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            $this->setState($provider, self::STATE_OPEN);
            $this->setLastFailureTime($provider);
        } else {
            $failureCount = $this->incrementFailureCount($provider);
            if ($failureCount >= $this->failureThreshold) {
                $this->setState($provider, self::STATE_OPEN);
                $this->setLastFailureTime($provider);
            }
        }
    }

    private function shouldAttemptReset(string $provider): bool
    {
        $lastFailureTime = (int) $this->cache->get($this->lastFailureTimeKey($provider));
        if ($lastFailureTime === 0) {
            return true;
        }

        return (time() - $lastFailureTime) >= $this->resetTimeout;
    }

    private function getRetryAfter(string $provider): int
    {
        $lastFailureTime = (int) $this->cache->get($this->lastFailureTimeKey($provider));
        if ($lastFailureTime === 0) {
            return 0;
        }

        return max(0, $this->resetTimeout - (time() - $lastFailureTime));
    }

    private function setState(string $provider, string $state): void
    {
        $this->cache->set($this->stateKey($provider), $state);
    }

    private function setLastFailureTime(string $provider): void
    {
        $this->cache->set($this->lastFailureTimeKey($provider), (string) time());
    }

    private function incrementFailureCount(string $provider): int
    {
        $key = $this->failureCountKey($provider);
        $count = (int) $this->cache->get($key);
        $this->cache->set($key, (string) ($count + 1));

        return $count + 1;
    }

    private function resetFailureCount(string $provider): void
    {
        $this->cache->set($this->failureCountKey($provider), '0');
    }

    private function incrementSuccessCount(string $provider): int
    {
        $key = $this->successCountKey($provider);
        $count = (int) $this->cache->get($key);
        $this->cache->set($key, (string) ($count + 1));

        return $count + 1;
    }

    private function stateKey(string $provider): string
    {
        return "circuit_breaker:state:{$provider}";
    }

    private function failureCountKey(string $provider): string
    {
        return "circuit_breaker:failures:{$provider}";
    }

    private function successCountKey(string $provider): string
    {
        return "circuit_breaker:successes:{$provider}";
    }

    private function lastFailureTimeKey(string $provider): string
    {
        return "circuit_breaker:last_failure:{$provider}";
    }
}
