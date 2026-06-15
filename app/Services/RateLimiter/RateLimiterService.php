<?php

namespace App\Services\RateLimiter;

use App\Services\Interfaces\CacheInterface;

/**
 * Rate Limiter для защиты от перегрузки marketing очереди.
 * Ограничивает количество сообщений на получателя в час.
 */
class RateLimiterService
{
    private const MARKETING_LIMIT_PER_HOUR = 5;
    private const CRITICAL_LIMIT_PER_MINUTE = 10;

    public function __construct(
        private CacheInterface $cache,
    ) {}

    /**
     * Проверка возможности отправки marketing сообщения.
     */
    public function canSendMarketing(string $recipientIdentifier): bool
    {
        return $this->canSend(
            recipientIdentifier: $recipientIdentifier,
            priority: 'marketing',
            limit: self::MARKETING_LIMIT_PER_HOUR,
            ttl: 3600
        );
    }

    /**
     * Проверка возможности отправки critical сообщения.
     */
    public function canSendCritical(string $recipientIdentifier): bool
    {
        return $this->canSend(
            recipientIdentifier: $recipientIdentifier,
            priority: 'critical',
            limit: self::CRITICAL_LIMIT_PER_MINUTE,
            ttl: 60
        );
    }

    /**
     * Получить оставшееся количество сообщений для получателя.
     */
    public function getRemainingLimit(string $recipientIdentifier, string $priority): int
    {
        $key = $this->getRateLimitKey(
            recipientIdentifier: $recipientIdentifier,
            priority: $priority
        );
        $count = (int) $this->cache->get($key);

        $limit = $priority === 'marketing'
            ? self::MARKETING_LIMIT_PER_HOUR
            : self::CRITICAL_LIMIT_PER_MINUTE;

        return max(0, $limit - $count);
    }

    /**
     * Общая проверка возможности отправки.
     */
    private function canSend(string $recipientIdentifier, string $priority, int $limit, int $ttl): bool
    {
        $key = $this->getRateLimitKey(
            recipientIdentifier: $recipientIdentifier,
            priority: $priority
        );
        $count = (int) $this->cache->get($key);

        if ($count >= $limit) {
            return false;
        }

        $this->cache->setex($key, $ttl, (string) ($count + 1));
        return true;
    }

    /**
     * Ключ для rate limit.
     */
    private function getRateLimitKey(string $recipientIdentifier, string $priority): string
    {
        return "rate_limit:{$priority}:{$recipientIdentifier}";
    }

    /**
     * Сбросить лимит для получателя.
     */
    public function reset(string $recipientIdentifier, string $priority): void
    {
        $key = $this->getRateLimitKey(
            recipientIdentifier: $recipientIdentifier,
            priority: $priority
        );
        $this->cache->del($key);
    }
}
