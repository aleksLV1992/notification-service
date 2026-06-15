<?php

declare(strict_types=1);

namespace App\Services\RateLimiter;

use App\Services\Interfaces\CacheInterface;

readonly class DeduplicationService
{
    private const KEY_PREFIX = 'notification:idempotency:';

    private const PENDING = 'pending';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function isDuplicate(string $idempotencyKey): bool
    {
        return $this->cache->exists($this->getKey($idempotencyKey));
    }

    public function tryReserve(string $idempotencyKey): bool
    {
        return $this->cache->setIfNotExists(
            key: $this->getKey($idempotencyKey),
            value: self::PENDING,
            ttl: $this->getTtl(),
        );
    }

    public function release(string $idempotencyKey): void
    {
        $this->cache->delete($this->getKey($idempotencyKey));
    }

    public function markAsProcessed(string $idempotencyKey, string $notificationId): void
    {
        $this->cache->setex(
            key: $this->getKey($idempotencyKey),
            ttl: $this->getTtl(),
            value: $notificationId,
        );
    }

    public function getNotificationId(string $idempotencyKey): ?string
    {
        $result = $this->cache->get($this->getKey($idempotencyKey));

        if (! $result || $result === self::PENDING) {
            return null;
        }

        return (string) $result;
    }

    private function getKey(string $idempotencyKey): string
    {
        return self::KEY_PREFIX.$idempotencyKey;
    }

    private function getTtl(): int
    {
        return (int) config('notification.deduplication.ttl', 3600);
    }
}
