<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Interfaces\CacheInterface;
use App\Services\RateLimiter\DeduplicationService;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DeduplicationServiceTest extends TestCase
{
    private $cache;

    private DeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(CacheInterface::class);
        $this->service = new DeduplicationService($this->cache);
    }

    public function test_is_duplicate_returns_false_for_new_key(): void
    {
        $key = Str::uuid()->toString();

        $this->cache->shouldReceive('exists')->with("notification:idempotency:{$key}")->andReturn(0);

        $this->assertFalse($this->service->isDuplicate($key));
    }

    public function test_is_duplicate_returns_true_after_marking(): void
    {
        $key = Str::uuid()->toString();
        $notificationId = Str::uuid()->toString();

        $this->cache->shouldReceive('exists')->with("notification:idempotency:{$key}")->andReturn(1);
        $this->cache->shouldReceive('setex')->once();

        $this->service->markAsProcessed($key, $notificationId);

        $this->assertTrue($this->service->isDuplicate($key));
    }

    public function test_get_notification_id_returns_correct_id(): void
    {
        $key = Str::uuid()->toString();
        $notificationId = Str::uuid()->toString();

        $this->cache->shouldReceive('get')
            ->with("notification:idempotency:{$key}")
            ->andReturn($notificationId);

        $this->assertEquals($notificationId, $this->service->getNotificationId($key));
    }

    public function test_get_notification_id_returns_null_for_unknown_key(): void
    {
        $key = Str::uuid()->toString();

        $this->cache->shouldReceive('get')
            ->with("notification:idempotency:{$key}")
            ->andReturn(null);

        $this->assertNull($this->service->getNotificationId($key));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
