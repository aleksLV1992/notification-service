<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

interface RedisClientInterface
{
    public function get(string $key): ?string;

    public function set(string $key, mixed $value): bool;

    public function setex(string $key, int $ttl, mixed $value): bool;

    public function exists(string $key): int;

    public function del(string $key): int;

    public function setIfNotExists(string $key, mixed $value, int $ttl): bool;
}
