<?php

namespace App\Services\Redis;

use App\Services\Interfaces\RedisClientInterface;
use Illuminate\Support\Facades\Log;

/**
 * Redis реализация интерфейса RedisClientInterface.
 * Использует PHP Redis extension вместо фасада Laravel.
 */
class RedisClientService implements RedisClientInterface
{
    public function __construct(
        private \Redis $redis,
    ) {}

    public function get(string $key): ?string
    {
        try {
            $result = $this->redis->get($key);
            return $result ?: null;
        } catch (\RedisException $e) {
            Log::error('Redis GET failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function set(string $key, mixed $value): bool
    {
        try {
            return $this->redis->set($key, (string) $value);
        } catch (\RedisException $e) {
            Log::error('Redis SET failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function setex(string $key, int $ttl, mixed $value): bool
    {
        try {
            return $this->redis->setEx($key, $ttl, (string) $value);
        } catch (\RedisException $e) {
            Log::error('Redis SETEX failed', [
                'key' => $key,
                'ttl' => $ttl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function exists(string $key): int
    {
        try {
            return $this->redis->exists($key);
        } catch (\RedisException $e) {
            Log::error('Redis EXISTS failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function del(string $key): int
    {
        try {
            return $this->redis->del($key);
        } catch (\RedisException $e) {
            Log::error('Redis DEL failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
