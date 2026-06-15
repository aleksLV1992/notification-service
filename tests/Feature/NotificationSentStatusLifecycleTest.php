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
use App\Services\Interfaces\MetricsInterface;
use App\Services\Providers\NotificationProviderInterface;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class NotificationSentStatusLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_reaches_sent_before_delivered_on_confirmation_retry(): void
    {
        Queue::fake();

        $confirmAttempts = 0;
        $provider = Mockery::mock(NotificationProviderInterface::class);
        $provider->shouldReceive('send')->once()->andReturn(true);
        $provider->shouldReceive('confirmDelivery')->twice()->andReturnUsing(function () use (&$confirmAttempts): bool {
            $confirmAttempts++;

            return $confirmAttempts >= 2;
        });
        $provider->shouldReceive('getProviderName')->andReturn('sms_mock');

        app(NotificationProviderFactory::class)->register('sms', $provider);

        $response = $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Sent lifecycle',
            'recipients' => ['+79990000099'],
            'priority' => 'normal',
        ]);

        $response->assertStatus(201);

        $recipient = NotificationRecipient::query()->first();
        $this->assertSame(Status::QUEUED, $recipient->status);

        $job = new SendNotificationJob(
            recipientId: $recipient->id,
            channel: 'sms',
            message: 'Sent lifecycle',
            priority: Priority::NORMAL,
            notificationId: $recipient->notification_id,
        );

        try {
            $job->handle(
                app(SendNotificationAction::class),
                app(MetricsInterface::class),
                app(NotificationRecipientRepositoryInterface::class),
            );
        } catch (Exception) {
            // confirmDelivery failed on first attempt
        }

        $recipient->refresh();
        $this->assertSame(Status::SENT, $recipient->status);
        $this->assertNotNull($recipient->sent_at);
        $this->assertNull($recipient->delivered_at);

        $job->handle(
            app(SendNotificationAction::class),
            app(MetricsInterface::class),
            app(NotificationRecipientRepositoryInterface::class),
        );

        $recipient->refresh();
        $this->assertSame(Status::DELIVERED, $recipient->status);
        $this->assertNotNull($recipient->delivered_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
