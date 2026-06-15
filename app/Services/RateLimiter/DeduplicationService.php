<?php

namespace App\Services\RateLimiter;

use App\Services\Interfaces\CacheInterface;

class DeduplicationService
{
    private const KEY_PREFIX = 'notification:idempotency:';
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function isDuplicate(string $idempotencyKey): bool
    {
        $key = $this->getKey($idempotencyKey);
        return $this->cache->exists($key);
    }

    public function markAsProcessed(string $idempotencyKey, string $notificationId): void
    {
        $key = $this->getKey($idempotencyKey);
        $this->cache->setex(
            key: $key,
            ttl: self::TTL_SECONDS,
            value: $notificationId
        );
    }

    public function getNotificationId(string $idempotencyKey): ?string
    {
        $key = $this->getKey($idempotencyKey);
        $result = $this->cache->get($key);
        return $result ?: null;
    }

    private function getKey(string $idempotencyKey): string
    {
        return self::KEY_PREFIX . $idempotencyKey;
    }
}
