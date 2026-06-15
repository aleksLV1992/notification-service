<?php

declare(strict_types=1);

namespace App\Services\RateLimiter;

use App\Services\Interfaces\CacheInterface;

readonly class RateLimiterService
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function exceedsMarketingLimit(string $recipientIdentifier): bool
    {
        return $this->getCount($recipientIdentifier, 'marketing') >= $this->marketingLimit();
    }

    public function recordMarketingSend(string $recipientIdentifier): void
    {
        $this->incrementCount(
            recipientIdentifier: $recipientIdentifier,
            priority: 'marketing',
            ttl: $this->marketingWindow(),
        );
    }

    public function canSendCritical(string $recipientIdentifier): bool
    {
        return $this->getCount($recipientIdentifier, 'critical') < $this->criticalLimit();
    }

    public function recordCriticalSend(string $recipientIdentifier): void
    {
        $this->incrementCount(
            recipientIdentifier: $recipientIdentifier,
            priority: 'critical',
            ttl: $this->criticalWindow(),
        );
    }

    public function getRemainingLimit(string $recipientIdentifier, string $priority): int
    {
        $count = $this->getCount($recipientIdentifier, $priority);
        $limit = $priority === 'marketing'
            ? $this->marketingLimit()
            : $this->criticalLimit();

        return max(0, $limit - $count);
    }

    public function reset(string $recipientIdentifier, string $priority): void
    {
        $this->cache->delete($this->getRateLimitKey($recipientIdentifier, $priority));
    }

    private function getCount(string $recipientIdentifier, string $priority): int
    {
        return (int) $this->cache->get($this->getRateLimitKey($recipientIdentifier, $priority));
    }

    private function incrementCount(string $recipientIdentifier, string $priority, int $ttl): void
    {
        $key = $this->getRateLimitKey($recipientIdentifier, $priority);
        $count = $this->getCount($recipientIdentifier, $priority);
        $this->cache->setex($key, $ttl, (string) ($count + 1));
    }

    private function getRateLimitKey(string $recipientIdentifier, string $priority): string
    {
        return "rate_limit:{$priority}:{$recipientIdentifier}";
    }

    private function marketingLimit(): int
    {
        return (int) config('notification.rate_limiter.marketing_limit', 100);
    }

    private function marketingWindow(): int
    {
        return (int) config('notification.rate_limiter.marketing_window', 3600);
    }

    private function criticalLimit(): int
    {
        return (int) config('notification.rate_limiter.critical_limit', 10);
    }

    private function criticalWindow(): int
    {
        return (int) config('notification.rate_limiter.critical_window', 60);
    }
}
