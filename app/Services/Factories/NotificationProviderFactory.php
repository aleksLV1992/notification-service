<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Services\Providers\NotificationProviderInterface;
use InvalidArgumentException;

class NotificationProviderFactory
{
    private array $providers = [];

    public function register(string $channel, NotificationProviderInterface $provider): void
    {
        $this->providers[$channel] = $provider;
    }

    public function get(string $channel): NotificationProviderInterface
    {
        if (! isset($this->providers[$channel])) {
            throw new InvalidArgumentException("Unknown notification channel: {$channel}");
        }

        return $this->providers[$channel];
    }

    public function supports(string $channel): bool
    {
        return isset($this->providers[$channel]);
    }

    public function getSupportedChannels(): array
    {
        return array_keys($this->providers);
    }
}
