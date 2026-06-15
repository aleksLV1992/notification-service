<?php

namespace App\Services;

use App\Exceptions\CircuitBreakerOpenException;
use App\Services\Interfaces\CacheInterface;

/**
 * Circuit Breaker для защиты от cascade failure.
 *
 * Состояния:
 * - CLOSED: нормальная работа, ошибки подсчитываются
 * - OPEN: провайдер недоступен, все запросы отклоняются
 * - HALF_OPEN: проверка доступности провайдера
 */
class CircuitBreakerService
{
    private int $failureThreshold;
    private int $successThreshold;
    private int $resetTimeout;

    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private CacheInterface $cache,
    ) {
        $this->failureThreshold = (int) config('notification.circuit_breaker.failure_threshold', 5);
        $this->successThreshold = (int) config('notification.circuit_breaker.success_threshold', 2);
        $this->resetTimeout = (int) config('notification.circuit_breaker.reset_timeout', 30);
    }

    /**
     * Выполнить вызов с защитой Circuit Breaker.
     */
    public function call(string $provider, callable $callback): mixed
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset($provider)) {
                $this->setState(
                    provider: $provider,
                    state: self::STATE_HALF_OPEN
                );
            } else {
                $retryAfter = $this->getRetryAfter($provider);
                throw new CircuitBreakerOpenException($provider, $retryAfter);
            }
        }

        try {
            $result = $callback();
            $this->onSuccess($provider);
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($provider);
            throw $e;
        }
    }

    /**
     * Получить текущее состояние.
     */
    public function getState(string $provider): string
    {
        $state = $this->cache->get($this->stateKey($provider));
        return $state ?? self::STATE_CLOSED;
    }

    /**
     * Обработка успешного вызова.
     */
    private function onSuccess(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = $this->incrementSuccessCount($provider);
            if ($successCount >= $this->successThreshold) {
                $this->setState(
                    provider: $provider,
                    state: self::STATE_CLOSED
                );
                $this->resetFailureCount($provider);
            }
        } else {
            $this->resetFailureCount($provider);
        }
    }

    /**
     * Обработка неудачного вызова.
     */
    private function onFailure(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            $this->setState(
                provider: $provider,
                state: self::STATE_OPEN
            );
            $this->setLastFailureTime($provider);
        } else {
            $failureCount = $this->incrementFailureCount($provider);
            if ($failureCount >= $this->failureThreshold) {
                $this->setState(
                    provider: $provider,
                    state: self::STATE_OPEN
                );
                $this->setLastFailureTime($provider);
            }
        }
    }

    /**
     * Проверка возможности попытки сброса.
     */
    private function shouldAttemptReset(string $provider): bool
    {
        $lastFailureTime = (int) $this->cache->get($this->lastFailureTimeKey($provider));
        if ($lastFailureTime === 0) {
            return true;
        }

        return (time() - $lastFailureTime) >= $this->resetTimeout;
    }

    /**
     * Получить время до следующей попытки.
     */
    private function getRetryAfter(string $provider): int
    {
        $lastFailureTime = (int) $this->cache->get($this->lastFailureTimeKey($provider));
        if ($lastFailureTime === 0) {
            return 0;
        }

        $elapsed = time() - $lastFailureTime;
        return max(0, $this->resetTimeout - $elapsed);
    }

    /**
     * Установить состояние.
     */
    private function setState(string $provider, string $state): void
    {
        $this->cache->set(
            key: $this->stateKey($provider),
            value: $state,
        );
    }

    /**
     * Установить время последнего сбоя.
     */
    private function setLastFailureTime(string $provider): void
    {
        $this->cache->set(
            key: $this->lastFailureTimeKey($provider),
            value: (string) time(),
        );
    }

    /**
     * Инкремент счётчика сбоев.
     */
    private function incrementFailureCount(string $provider): int
    {
        $key = $this->failureCountKey($provider);
        $count = (int) $this->cache->get($key);
        $this->cache->set(
            key: $key,
            value: (string) ($count + 1),
        );
        return $count + 1;
    }

    /**
     * Сброс счётчика сбоев.
     */
    private function resetFailureCount(string $provider): void
    {
        $this->cache->set(
            key: $this->failureCountKey($provider),
            value: '0',
        );
    }

    /**
     * Инкремент счётчика успехов.
     */
    private function incrementSuccessCount(string $provider): int
    {
        $key = $this->successCountKey($provider);
        $count = (int) $this->cache->get($key);
        $this->cache->set(
            key: $key,
            value: (string) ($count + 1),
        );
        return $count + 1;
    }

    /**
     * Ключ для состояния.
     */
    private function stateKey(string $provider): string
    {
        return "circuit_breaker:state:{$provider}";
    }

    /**
     * Ключ для счётчика сбоев.
     */
    private function failureCountKey(string $provider): string
    {
        return "circuit_breaker:failures:{$provider}";
    }

    /**
     * Ключ для счётчика успехов.
     */
    private function successCountKey(string $provider): string
    {
        return "circuit_breaker:successes:{$provider}";
    }

    /**
     * Ключ для времени последнего сбоя.
     */
    private function lastFailureTimeKey(string $provider): string
    {
        return "circuit_breaker:last_failure:{$provider}";
    }

    /**
     * Получить статистику Circuit Breaker.
     */
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
}
