<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Factories\NotificationProviderResolver;
use App\Services\Providers\EmailLogProvider;
use App\Services\Providers\EmailMockProvider;
use App\Services\Providers\SmsLogProvider;
use App\Services\Providers\SmsMockProvider;
use Tests\TestCase;

class NotificationProviderResolverTest extends TestCase
{
    public function test_resolves_sms_mock_driver(): void
    {
        config(['notification.providers.sms.driver' => 'mock']);

        $provider = app(NotificationProviderResolver::class)->resolve('sms');

        $this->assertInstanceOf(SmsMockProvider::class, $provider);
    }

    public function test_resolves_sms_log_driver(): void
    {
        config(['notification.providers.sms.driver' => 'log']);

        $provider = app(NotificationProviderResolver::class)->resolve('sms');

        $this->assertInstanceOf(SmsLogProvider::class, $provider);
    }

    public function test_resolves_email_log_driver(): void
    {
        config(['notification.providers.email.driver' => 'log']);

        $provider = app(NotificationProviderResolver::class)->resolve('email');

        $this->assertInstanceOf(EmailLogProvider::class, $provider);
    }

    public function test_resolves_email_mock_driver(): void
    {
        config(['notification.providers.email.driver' => 'mock']);

        $provider = app(NotificationProviderResolver::class)->resolve('email');

        $this->assertInstanceOf(EmailMockProvider::class, $provider);
    }
}
