<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Services\Providers\EmailLogProvider;
use App\Services\Providers\EmailMockProvider;
use App\Services\Providers\NotificationProviderInterface;
use App\Services\Providers\SmsLogProvider;
use App\Services\Providers\SmsMockProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class NotificationProviderResolver
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function resolve(string $channel): NotificationProviderInterface
    {
        $driver = (string) config("notification.providers.{$channel}.driver", 'mock');

        return match ("{$channel}.{$driver}") {
            'sms.mock' => $this->container->make(SmsMockProvider::class),
            'sms.log' => $this->container->make(SmsLogProvider::class),
            'email.mock' => $this->container->make(EmailMockProvider::class),
            'email.log' => $this->container->make(EmailLogProvider::class),
            default => throw new InvalidArgumentException("Unsupported notification provider: {$channel}.{$driver}"),
        };
    }
}
