<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function setex(string $key, int $ttl, mixed $value): bool;

    public function exists(string $key): bool;

    public function delete(string $key): bool;

    public function setIfNotExists(string $key, mixed $value, int $ttl): bool;
}
