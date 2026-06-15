<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Events\NotificationDelivered;
use App\Events\NotificationFailed;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

trait InteractsWithEvents
{
    protected function fakeAllEvents(): void
    {
        Event::fake();
    }

    protected function fakeEvents(array $events): void
    {
        Event::fake($events);
    }

    protected function assertEventDispatched(string $event, ?callable $callback = null): void
    {
        if ($callback) {
            Event::assertDispatched($event, $callback);
        } else {
            Event::assertDispatched($event);
        }
    }

    protected function assertEventNotDispatched(string $event): void
    {
        Event::assertNotDispatched($event);
    }

    protected function assertEventDispatchedTimes(string $event, int $times = 1): void
    {
        Event::assertDispatchedTimes($event, $times);
    }

    protected function assertNotificationSent(?callable $callback = null): void
    {
        $this->assertEventDispatched(NotificationSent::class, $callback);
    }

    protected function assertNotificationDelivered(?callable $callback = null): void
    {
        $this->assertEventDispatched(NotificationDelivered::class, $callback);
    }

    protected function assertNotificationFailed(?callable $callback = null): void
    {
        $this->assertEventDispatched(NotificationFailed::class, $callback);
    }
}
