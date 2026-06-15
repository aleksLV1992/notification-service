<?php

namespace App\Services\Interfaces;

/**
 * Интерфейс для Redis клиента.
 * Абстрагирует Redis для лучшей тестируемости и DI.
 */
interface RedisClientInterface
{
    /**
     * Получить значение по ключу.
     */
    public function get(string $key): ?string;

    /**
     * Установить значение по ключу.
     */
    public function set(string $key, mixed $value): bool;

    /**
     * Установить значение с TTL (секунды).
     */
    public function setex(string $key, int $ttl, mixed $value): bool;

    /**
     * Проверить существование ключа.
     */
    public function exists(string $key): int;

    /**
     * Удалить ключ.
     */
    public function del(string $key): int;
}
