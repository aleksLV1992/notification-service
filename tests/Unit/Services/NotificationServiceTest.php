<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\BulkNotificationData;
use App\DTO\NotificationResult;
use App\Enums\Priority;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\Interfaces\CacheInterface;
use App\Services\Metrics\MetricsService;
use App\Services\NotificationService;
use App\Services\RateLimiter\DeduplicationService;
use App\Services\RateLimiter\RateLimiterService;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Services\NotificationService
 */
class NotificationServiceTest extends TestCase
{
    private $notificationRepo;
    private $recipientRepo;
    private $cache;
    private DeduplicationService $deduplicationService;
    private $providerFactory;
    private $rateLimiter;
    private $metrics;
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationRepo = Mockery::mock(NotificationRepositoryInterface::class);
        $this->recipientRepo = Mockery::mock(NotificationRecipientRepositoryInterface::class);
        $this->cache = Mockery::mock(CacheInterface::class);
        $this->deduplicationService = new DeduplicationService($this->cache);
        $this->providerFactory = Mockery::mock(NotificationProviderFactory::class);
        $this->rateLimiter = Mockery::mock(RateLimiterService::class);
        $this->metrics = Mockery::mock(MetricsService::class);

        $this->service = new NotificationService(
            $this->notificationRepo,
            $this->recipientRepo,
            $this->deduplicationService,
            $this->providerFactory,
            $this->rateLimiter,
            $this->metrics
        );
    }

    /**
     * @test Создание массового уведомления
     */
    public function testSendBulkNotificationCreatesNotification(): void
    {
        $data = new BulkNotificationData(
            channel: 'sms',
            message: 'Тест',
            recipients: ['+79991234567'],
            priority: 'normal',
            idempotency_key: Str::uuid()->toString()
        );

        $notification = Mockery::mock(\App\Models\Notification::class)->makePartial();
        $notification->id = Str::uuid()->toString();
        $notification->channel = 'sms';
        $notification->priority = 'normal';
        $notification->batch_id = Str::uuid()->toString();
        $notification->recipients = collect([]);
        $notification->shouldReceive('getQueueName')->andReturn('default');

        $this->cache->shouldReceive('exists')->andReturn(0);
        $this->cache->shouldReceive('setex')->andReturn(true);
        $this->notificationRepo->shouldReceive('createWithRecipients')->andReturn($notification);
        $this->recipientRepo->shouldReceive('countByNotificationId')->andReturn(1);
        $this->rateLimiter->shouldReceive('canSendMarketing')->andReturn(true);
        $this->metrics->shouldReceive('incrementNotificationSent')->once();

        $result = $this->service->sendBulkNotification($data);

        $this->assertFalse($result->isDuplicate);
        $this->assertFalse($result->isRateLimitExceeded);
    }

    /**
     * @test Дубликат уведомления
     */
    public function testSendBulkNotificationReturnsExistingForDuplicate(): void
    {
        $data = new BulkNotificationData(
            channel: 'sms',
            message: 'Тест',
            recipients: ['+79991234567'],
            priority: 'normal',
            idempotency_key: 'dup-key'
        );

        $notification = Mockery::mock(\App\Models\Notification::class)->makePartial();
        $notification->id = 'existing-id';

        $this->cache->shouldReceive('exists')->andReturn(1);
        $this->cache->shouldReceive('get')->andReturn('existing-id');
        $this->notificationRepo->shouldReceive('findById')->with('existing-id')->andReturn($notification);
        $this->metrics->shouldReceive('incrementDuplicateDetected')->once();

        $result = $this->service->sendBulkNotification($data);

        $this->assertTrue($result->isDuplicate);
    }

    /**
     * @test Rate limit exceeded
     */
    public function testSendBulkNotificationFailsOnRateLimitExceeded(): void
    {
        $data = new BulkNotificationData(
            channel: 'sms',
            message: 'Marketing',
            recipients: ['+79991234567'],
            priority: 'marketing',
            idempotency_key: Str::uuid()->toString()
        );

        $this->cache->shouldReceive('exists')->andReturn(0);
        $this->cache->shouldReceive('setex')->andReturn(true);
        $this->rateLimiter->shouldReceive('canSendMarketing')->andReturn(false);
        $this->metrics->shouldReceive('incrementRateLimitExceeded')->once();

        $result = $this->service->sendBulkNotification($data);

        $this->assertTrue($result->isRateLimitExceeded);
    }

    /**
     * @test Получение статуса
     */
    public function testGetNotificationStatusReturnsNullForNonexistent(): void
    {
        $this->notificationRepo
            ->shouldReceive('getRecipientStatus')
            ->andReturn(null);

        $result = $this->service->getNotificationStatus('id', 'recipient');

        $this->assertNull($result);
    }

    /**
     * @test Получение истории
     */
    public function testGetRecipientHistory(): void
    {
        $paginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
        $paginator->shouldReceive('toArray')->andReturn(['data' => []]);

        $this->notificationRepo
            ->shouldReceive('findRecipientHistory')
            ->andReturn($paginator);

        $result = $this->service->getRecipientHistory('+79991234567', 10);

        $this->assertEquals(['data' => []], $result->toArray());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
