<?php

namespace Tests\Unit\Services;

use App\Services\RateLimiter\DeduplicationService;
use App\Services\Interfaces\CacheInterface;
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

    public function testIsDuplicateReturnsFalseForNewKey(): void
    {
        $key = Str::uuid()->toString();
        
        $this->cache->shouldReceive('exists')->with("notification:idempotency:{$key}")->andReturn(0);

        $this->assertFalse($this->service->isDuplicate($key));
    }

    public function testIsDuplicateReturnsTrueAfterMarking(): void
    {
        $key = Str::uuid()->toString();
        $notificationId = Str::uuid()->toString();

        $this->cache->shouldReceive('exists')->with("notification:idempotency:{$key}")->andReturn(1);
        $this->cache->shouldReceive('setex')->once();

        $this->service->markAsProcessed($key, $notificationId);
        
        $this->assertTrue($this->service->isDuplicate($key));
    }

    public function testGetNotificationIdReturnsCorrectId(): void
    {
        $key = Str::uuid()->toString();
        $notificationId = Str::uuid()->toString();

        $this->cache->shouldReceive('get')
            ->with("notification:idempotency:{$key}")
            ->andReturn($notificationId);

        $this->assertEquals($notificationId, $this->service->getNotificationId($key));
    }

    public function testGetNotificationIdReturnsNullForUnknownKey(): void
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
