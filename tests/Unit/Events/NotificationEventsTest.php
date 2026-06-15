<?php

namespace Tests\Unit\Events;

use App\Enums\Status;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\CreatesNotifications;
use Tests\Traits\InteractsWithEvents;

class NotificationEventsTest extends TestCase
{
    use RefreshDatabase, CreatesNotifications, InteractsWithEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeAllEvents();
    }

    public function testNotificationSentEventDispatched(): void
    {
        $notification = $this->createTestNotification([
            'channel' => 'sms',
            'message' => 'Test message',
            'priority' => 'critical',
        ]);

        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => '+1234567890',
            'status' => Status::SENT->value,
            'attempts' => 0,
        ]);

        $recipient->markAsSent();

        $this->assertNotificationSent(function ($event) use ($recipient) {
            return $event->recipient->id === $recipient->id;
        });
    }

    public function testNotificationDeliveredEventDispatched(): void
    {
        $notification = $this->createTestNotification([
            'channel' => 'email',
            'message' => 'Test message',
            'priority' => 'normal',
        ]);

        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => 'test@example.com',
            'status' => Status::SENT->value,
            'attempts' => 1,
        ]);

        $recipient->markAsDelivered();

        $this->assertNotificationDelivered(function ($event) use ($recipient) {
            return $event->recipient->id === $recipient->id;
        });
    }

    public function testNotificationFailedEventDispatched(): void
    {
        $notification = $this->createTestNotification([
            'channel' => 'sms',
            'message' => 'Test message',
            'priority' => 'marketing',
        ]);

        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => '+1234567890',
            'status' => Status::QUEUED->value,
            'attempts' => 3,
        ]);

        $errorMessage = 'Provider timeout';
        $recipient->markAsFailed($errorMessage);

        $this->assertNotificationFailed(function ($event) use ($recipient, $errorMessage) {
            return $event->recipient->id === $recipient->id
                && $event->errorMessage === $errorMessage;
        });
    }

    public function testNotificationSentEventContainsCorrectData(): void
    {
        $notification = $this->createTestNotification([
            'channel' => 'sms',
            'message' => 'Test message',
            'priority' => 'critical',
        ]);

        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => '+1234567890',
            'status' => Status::QUEUED->value,
            'attempts' => 0,
        ]);

        $recipient->markAsSent();

        $this->assertNotificationSent(function ($event) use ($recipient) {
            return $event->recipient->id === $recipient->id;
        });
    }

    public function testNotificationFailedEventContainsErrorMessage(): void
    {
        $notification = $this->createTestNotification([
            'channel' => 'email',
            'message' => 'Test message',
            'priority' => 'normal',
        ]);

        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => 'test@example.com',
            'status' => Status::QUEUED->value,
            'attempts' => 3,
        ]);

        $errorMessage = 'SMTP connection failed';
        $recipient->markAsFailed($errorMessage);

        $this->assertNotificationFailed(function ($event) use ($errorMessage) {
            return $event->errorMessage === $errorMessage;
        });
    }
}
