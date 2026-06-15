<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Notification\SendNotificationAction;
use App\Enums\Priority;
use App\Enums\Status;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\MetricsInterface;
use App\Services\Providers\NotificationProviderInterface;
use App\Services\Providers\SmsMockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class NotificationDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = Mockery::mock(NotificationProviderInterface::class);
        $provider->shouldReceive('send')->andReturn(true);
        $provider->shouldReceive('confirmDelivery')->andReturn(true);
        $provider->shouldReceive('getProviderName')->andReturn('sms_mock');

        app(NotificationProviderFactory::class)->register('sms', $provider);
    }

    public function test_bulk_notification_processed_through_queue_to_delivered(): void
    {
        $response = $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Код подтверждения: 123456',
            'recipients' => ['+79991234567'],
            'priority' => 'critical',
        ]);

        $response->assertStatus(201);

        $notificationId = $response->json('data.id');
        $recipient = NotificationRecipient::query()
            ->where('notification_id', $notificationId)
            ->first();

        $this->assertNotNull($recipient);
        $this->assertSame(Status::DELIVERED, $recipient->fresh()->status);
        $this->assertNotNull($recipient->fresh()->sent_at);
        $this->assertNotNull($recipient->fresh()->delivered_at);
    }

    public function test_duplicate_request_returns_conflict(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'channel' => 'sms',
            'message' => 'Тест дедубликации',
            'recipients' => ['+79990000001'],
            'priority' => 'normal',
            'idempotency_key' => $idempotencyKey,
        ];

        $this->postJson('/api/v1/notifications/bulk', $payload)->assertStatus(201);

        $duplicate = $this->postJson('/api/v1/notifications/bulk', $payload);

        $duplicate->assertStatus(409)
            ->assertJsonPath('meta.status', 'duplicate');
    }

    public function test_marketing_rate_limit_returns429(): void
    {
        config([
            'notification.rate_limiter.marketing_limit' => 1,
            'notification.rate_limiter.marketing_window' => 3600,
        ]);

        $payload = [
            'channel' => 'sms',
            'message' => 'Marketing promo',
            'recipients' => ['+79995555555'],
            'priority' => 'marketing',
        ];

        $this->postJson('/api/v1/notifications/bulk', $payload)->assertStatus(201);
        $this->postJson('/api/v1/notifications/bulk', $payload)->assertStatus(429);
    }

    public function test_failed_delivery_marks_recipient_as_dropped(): void
    {
        Queue::fake();

        $provider = Mockery::mock(NotificationProviderInterface::class);
        $provider->shouldReceive('send')->andReturn(false);
        $provider->shouldReceive('getProviderName')->andReturn('sms_mock');

        app(NotificationProviderFactory::class)->register('sms', $provider);

        $response = $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Fail test',
            'recipients' => ['+79991111111'],
            'priority' => 'normal',
        ]);

        $response->assertStatus(201);

        $recipient = NotificationRecipient::query()->first();
        $this->assertSame(Status::QUEUED, $recipient->fresh()->status);

        $job = new SendNotificationJob(
            recipientId: $recipient->id,
            channel: 'sms',
            message: 'Fail test',
            priority: Priority::NORMAL,
            notificationId: $recipient->notification_id,
        );

        try {
            $job->handle(
                app(SendNotificationAction::class),
                app(MetricsInterface::class),
                app(NotificationRecipientRepositoryInterface::class),
            );
        } catch (\Exception) {
            // expected retry signal
        }

        $job->failed(new \Exception('Failed to send notification'));

        $this->assertSame(Status::DROPPED, $recipient->fresh()->status);
        $this->assertGreaterThanOrEqual(1, $recipient->fresh()->attempts);
    }

    public function test_circuit_breaker_open_marks_recipient_as_dropped_without_retries(): void
    {
        $cache = app(CacheInterface::class);
        $providerName = 'sms_mock';
        $cache->set("circuit_breaker:state:{$providerName}", 'open');
        $cache->set("circuit_breaker:last_failure:{$providerName}", (string) time());

        app(NotificationProviderFactory::class)->register('sms', app(SmsMockProvider::class));

        $response = $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'CB test',
            'recipients' => ['+79992222222'],
            'priority' => 'critical',
        ]);

        $response->assertStatus(201);

        $recipient = NotificationRecipient::query()->first();
        $this->assertSame(Status::DROPPED, $recipient->fresh()->status);
        $this->assertSame(1, $recipient->fresh()->attempts);
    }

    public function test_job_idempotency_skips_already_delivered_recipient(): void
    {
        $response = $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Idempotent job',
            'recipients' => ['+79993333333'],
            'priority' => 'normal',
        ]);

        $response->assertStatus(201);

        $recipient = NotificationRecipient::query()->first();
        $this->assertSame(Status::DELIVERED, $recipient->fresh()->status);

        $provider = Mockery::mock(NotificationProviderInterface::class);
        $provider->shouldReceive('send')->never();
        $provider->shouldReceive('confirmDelivery')->never();
        $provider->shouldReceive('getProviderName')->andReturn('sms_mock');

        app(NotificationProviderFactory::class)->register('sms', $provider);

        SendNotificationJob::dispatch(
            recipientId: $recipient->id,
            channel: 'sms',
            message: 'Idempotent job',
            priority: Priority::NORMAL,
            notificationId: $recipient->notification_id,
        );

        $this->assertSame(Status::DELIVERED, $recipient->fresh()->status);
        $this->assertSame(1, $recipient->fresh()->attempts);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
