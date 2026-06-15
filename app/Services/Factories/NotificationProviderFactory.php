<?php

namespace App\Services\Factories;

use App\Services\Providers\NotificationProviderInterface;
use InvalidArgumentException;

class NotificationProviderFactory
{
    /**
     * @var array<string, NotificationProviderInterface>
     */
    private array $providers = [];

    /**
     * Зарегистрировать провайдера для канала.
     */
    public function register(string $channel, NotificationProviderInterface $provider): void
    {
        $this->providers[$channel] = $provider;
    }

    /**
     * Получить провайдера для канала.
     */
    public function get(string $channel): NotificationProviderInterface
    {
        if (!isset($this->providers[$channel])) {
            throw new InvalidArgumentException("Unknown notification channel: {$channel}");
        }

        return $this->providers[$channel];
    }

    /**
     * Создать провайдер уведомлений по каналу (алиас для get).
     *
     * @deprecated Используйте get()
     */
    public function create(string $channel): NotificationProviderInterface
    {
        return $this->get($channel);
    }

    /**
     * Проверка поддержки канала.
     */
    public function supports(string $channel): bool
    {
        return isset($this->providers[$channel]);
    }

    /**
     * Получить список поддерживаемых каналов.
     *
     * @return array<string>
     */
    public function getSupportedChannels(): array
    {
        return array_keys($this->providers);
    }
}
