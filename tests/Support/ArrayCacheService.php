<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Interfaces\CacheInterface;

class ArrayCacheService implements CacheInterface
{
    private array $store = [];

    private array $ttl = [];

    public function get(string $key): mixed
    {
        if (isset($this->ttl[$key]) && $this->ttl[$key] < time()) {
            unset($this->store[$key], $this->ttl[$key]);

            return null;
        }

        return $this->store[$key] ?? null;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->store[$key] = $value;
        if ($ttl !== null) {
            $this->ttl[$key] = time() + $ttl;
        }

        return true;
    }

    public function setex(string $key, int $ttl, mixed $value): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function exists(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key], $this->ttl[$key]);

        return true;
    }

    public function setIfNotExists(string $key, mixed $value, int $ttl): bool
    {
        if ($this->exists($key)) {
            return false;
        }

        return $this->setex($key, $ttl, $value);
    }
}
