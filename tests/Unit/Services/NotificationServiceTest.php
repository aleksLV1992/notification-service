<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\BulkNotificationData;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Interfaces\MetricsInterface;
use App\Services\NotificationService;
use App\Services\RateLimiter\DeduplicationService;
use App\Services\RateLimiter\RateLimiterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\ArrayCacheService;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private $notificationRepo;

    private $recipientRepo;

    private ArrayCacheService $cache;

    private DeduplicationService $deduplicationService;

    private RateLimiterService $rateLimiter;

    private $metrics;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationRepo = Mockery::mock(NotificationRepositoryInterface::class);
        $this->recipientRepo = Mockery::mock(NotificationRecipientRepositoryInterface::class);
        $this->cache = new ArrayCacheService;
        $this->deduplicationService = new DeduplicationService($this->cache);
        $this->rateLimiter = new RateLimiterService($this->cache);
        $this->metrics = Mockery::mock(MetricsInterface::class);

        $this->service = new NotificationService(
            $this->notificationRepo,
            $this->recipientRepo,
            $this->deduplicationService,
            $this->rateLimiter,
            $this->metrics,
        );
    }

    public function test_send_bulk_notification_creates_notification(): void
    {
        $data = new BulkNotificationData(
            channel: 'sms',
            message: 'Тест',
            recipients: ['+79991234567'],
            priority: 'normal',
            idempotency_key: Str::uuid()->toString(),
        );

        $notification = Mockery::mock(Notification::class)->makePartial();
        $notification->id = Str::uuid()->toString();
        $notification->channel = 'sms';
        $notification->priority = 'normal';
        $notification->batch_id = Str::uuid()->toString();
        $notification->recipients = collect([]);
        $notification->shouldReceive('getQueueName')->andReturn('default');

        $this->notificationRepo->shouldReceive('findByIdempotencyKey')->andReturn(null);
        $this->notificationRepo->shouldReceive('createWithRecipients')->andReturn($notification);

        $result = $this->service->sendBulkNotification($data);

        $this->assertFalse($result->isDuplicate);
        $this->assertFalse($result->isRateLimitExceeded);
    }

    public function test_send_bulk_notification_returns_existing_for_duplicate(): void
    {
        $data = new BulkNotificationData(
            channel: 'sms',
            message: 'Тест',
            recipients: ['+79991234567'],
            priority: 'normal',
            idempotency_key: 'dup-key',
        );

        $notification = Mockery::mock(Notification::class)->makePartial();
        $notification->id = 'existing-id';

        $this->notificationRepo->shouldReceive('findByIdempotencyKey')->with('dup-key')->andReturn($notification);
        $this->metrics->shouldReceive('incrementDuplicateDetected')->once();

        $result = $this->service->sendBulkNotification($data);

        $this->assertTrue($result->isDuplicate);
    }

    public function test_send_bulk_notification_fails_on_rate_limit_exceeded(): void
    {
        config(['notification.rate_limiter.marketing_limit' => 1]);

        $data = new BulkNotificationData(
            channel: 'sms',
            message: 'Marketing',
            recipients: ['+79991234567'],
            priority: 'marketing',
            idempotency_key: Str::uuid()->toString(),
        );

        $this->notificationRepo->shouldReceive('findByIdempotencyKey')->andReturn(null);
        $this->rateLimiter->recordMarketingSend('+79991234567');
        $this->metrics->shouldReceive('incrementRateLimitExceeded')->once();

        $result = $this->service->sendBulkNotification($data);

        $this->assertTrue($result->isRateLimitExceeded);
    }

    public function test_get_notification_status_returns_null_for_nonexistent(): void
    {
        $this->recipientRepo
            ->shouldReceive('getStatusForRecipient')
            ->andReturn(null);

        $result = $this->service->getNotificationStatus('id', 'recipient');

        $this->assertNull($result);
    }

    public function test_get_recipient_history(): void
    {
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('toArray')->andReturn(['data' => []]);

        $this->recipientRepo
            ->shouldReceive('findHistoryByRecipient')
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
