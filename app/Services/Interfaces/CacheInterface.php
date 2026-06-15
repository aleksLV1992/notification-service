<?php

namespace App\Services\Interfaces;

/**
 * Интерфейс для работы с кэшем.
 * Абстрагирует Redis/Cache для лучшей тестируемости и DI.
 */
interface CacheInterface
{
    /**
     * Получить значение из кэша.
     */
    public function get(string $key): mixed;

    /**
     * Установить значение в кэш.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Установить значение с TTL (секунды).
     */
    public function setex(string $key, int $ttl, mixed $value): bool;

    /**
     * Проверить существование ключа.
     */
    public function exists(string $key): bool;

    /**
     * Удалить ключ из кэша.
     */
    public function delete(string $key): bool;
}
