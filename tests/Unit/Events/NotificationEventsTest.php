<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Enums\Status;
use App\Models\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesNotifications;
use Tests\Traits\InteractsWithEvents;

class NotificationEventsTest extends TestCase
{
    use CreatesNotifications, InteractsWithEvents, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeAllEvents();
    }

    public function test_notification_sent_event_dispatched(): void
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

    public function test_notification_delivered_event_dispatched(): void
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

    public function test_notification_failed_event_dispatched(): void
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
        $recipient->markAsDropped($errorMessage);

        $this->assertNotificationFailed(function ($event) use ($recipient, $errorMessage) {
            return $event->recipient->id === $recipient->id
                && $event->errorMessage === $errorMessage;
        });
    }

    public function test_notification_sent_event_contains_correct_data(): void
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

    public function test_notification_failed_event_contains_error_message(): void
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
        $recipient->markAsDropped($errorMessage);

        $this->assertNotificationFailed(function ($event) use ($errorMessage) {
            return $event->errorMessage === $errorMessage;
        });
    }
}
