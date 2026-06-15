<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Metrics\MetricsService;
use Tests\Support\ArrayCacheService;
use Tests\TestCase;

class MetricsServiceTest extends TestCase
{
    public function test_get_metrics_exports_prometheus_counters(): void
    {
        $service = new MetricsService(new ArrayCacheService);

        $service->incrementNotificationSent('sms', 'critical');
        $service->incrementCircuitBreakerTriggered('sms_mock');

        $metrics = $service->getMetrics();

        $this->assertStringContainsString('notifications_sent_total', $metrics);
        $this->assertStringContainsString('channel="sms"', $metrics);
        $this->assertStringContainsString('circuit_breaker_triggered_total', $metrics);
        $this->assertStringContainsString('sms_mock', $metrics);
    }
}
