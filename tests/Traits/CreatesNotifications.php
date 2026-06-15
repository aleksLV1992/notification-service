<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Support\Str;

trait CreatesNotifications
{
    protected function createTestNotification(array $attributes = []): Notification
    {
        return Notification::factory()->create(array_merge([
            'channel' => 'sms',
            'message' => 'Test message',
            'priority' => 'normal',
        ], $attributes));
    }

    protected function createNotificationWithRecipients(
        array $notificationAttributes = [],
        array $recipients = ['+79991234567']
    ): Notification {
        $notification = $this->createTestNotification($notificationAttributes);

        foreach ($recipients as $recipient) {
            NotificationRecipient::factory()->create([
                'notification_id' => $notification->id,
                'recipient_identifier' => $recipient,
            ]);
        }

        return $notification->fresh();
    }

    protected function createCriticalNotification(array $attributes = []): Notification
    {
        return $this->createTestNotification(array_merge([
            'priority' => 'critical',
            'channel' => 'sms',
        ], $attributes));
    }

    protected function createMarketingNotification(array $attributes = []): Notification
    {
        return $this->createTestNotification(array_merge([
            'priority' => 'marketing',
            'channel' => 'email',
        ], $attributes));
    }

    protected function uuid(): string
    {
        return Str::uuid()->toString();
    }
}
