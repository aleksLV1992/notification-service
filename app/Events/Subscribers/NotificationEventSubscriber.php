<?php

namespace App\Events\Subscribers;

use App\Events\NotificationDelivered;
use App\Events\NotificationFailed;
use App\Events\NotificationSent;
use App\Services\Interfaces\MetricsInterface;

class NotificationEventSubscriber
{
    public function __construct(
        private MetricsInterface $metrics,
    ) {}

    public function onNotificationSent(NotificationSent $event): void
    {
        $this->metrics->incrementNotificationSent(
            $event->getChannel(),
            $event->getPriority()
        );
    }

    public function onNotificationDelivered(NotificationDelivered $event): void
    {
        $this->metrics->incrementNotificationDelivered(
            $event->getChannel(),
            $event->getPriority()
        );
    }

    public function onNotificationFailed(NotificationFailed $event): void
    {
        $this->metrics->incrementNotificationFailed(
            $event->getChannel(),
            $event->getPriority(),
            $event->getErrorMessage()
        );

        Log::error('Notification failed', [
            'recipient' => $event->getRecipientIdentifier(),
            'channel' => $event->getChannel(),
            'error' => $event->getErrorMessage(),
        ]);
    }

    public function subscribe(): array
    {
        return [
            NotificationSent::class => 'onNotificationSent',
            NotificationDelivered::class => 'onNotificationDelivered',
            NotificationFailed::class => 'onNotificationFailed',
        ];
    }
}
