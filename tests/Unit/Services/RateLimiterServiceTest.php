<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RateLimiter\RateLimiterService;
use Tests\Support\ArrayCacheService;
use Tests\TestCase;

class RateLimiterServiceTest extends TestCase
{
    private RateLimiterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notification.rate_limiter.marketing_limit' => 2,
            'notification.rate_limiter.marketing_window' => 3600,
            'notification.rate_limiter.critical_limit' => 3,
            'notification.rate_limiter.critical_window' => 60,
        ]);

        $this->service = new RateLimiterService(new ArrayCacheService);
    }

    public function test_marketing_limit_uses_config_value(): void
    {
        $recipient = '+79990000001';

        $this->assertFalse($this->service->exceedsMarketingLimit($recipient));
        $this->service->recordMarketingSend($recipient);
        $this->service->recordMarketingSend($recipient);
        $this->assertTrue($this->service->exceedsMarketingLimit($recipient));
    }

    public function test_critical_limit_uses_config_value(): void
    {
        $recipient = '+79990000002';

        $this->assertTrue($this->service->canSendCritical($recipient));
        $this->service->recordCriticalSend($recipient);
        $this->service->recordCriticalSend($recipient);
        $this->service->recordCriticalSend($recipient);
        $this->assertFalse($this->service->canSendCritical($recipient));
    }
}
