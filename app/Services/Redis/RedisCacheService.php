<?php

namespace App\Services\Redis;

use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\RedisClientInterface;

/**
 * Кэш сервис на основе Redis.
 * Использует DI через RedisClientInterface.
 */
class RedisCacheService implements CacheInterface
{
    public function __construct(
        private RedisClientInterface $redis,
    ) {}

    public function get(string $key): mixed
    {
        return $this->redis->get($key);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($ttl !== null) {
            return $this->setex(
                key: $key,
                ttl: $ttl,
                value: $value,
            );
        }
        return $this->redis->set(
            key: $key,
            value: $value,
        );
    }

    public function setex(string $key, int $ttl, mixed $value): bool
    {
        return $this->redis->setex(
            key: $key,
            ttl: $ttl,
            value: $value,
        );
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) === 1;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }
}
