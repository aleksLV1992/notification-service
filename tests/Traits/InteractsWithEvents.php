<?php

namespace Tests\Traits;

use App\Events\NotificationDelivered;
use App\Events\NotificationFailed;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

/**
 * Trait для тестирования событий
 */
trait InteractsWithEvents
{
    /**
     * Подделать все события
     */
    protected function fakeAllEvents(): void
    {
        Event::fake();
    }

    /**
     * Подделать конкретные события
     *
     * @param class-string[] $events
     */
    protected function fakeEvents(array $events): void
    {
        Event::fake($events);
    }

    /**
     * Assert что событие было отправлено
     *
     * @param class-string $event
     */
    protected function assertEventDispatched(string $event, ?callable $callback = null): void
    {
        if ($callback) {
            Event::assertDispatched($event, $callback);
        } else {
            Event::assertDispatched($event);
        }
    }

    /**
     * Assert что событие не было отправлено
     *
     * @param class-string $event
     */
    protected function assertEventNotDispatched(string $event): void
    {
        Event::assertNotDispatched($event);
    }

    /**
     * Assert что событие было отправлено указанное количество раз
     *
     * @param class-string $event
     */
    protected function assertEventDispatchedTimes(string $event, int $times = 1): void
    {
        Event::assertDispatchedTimes($event, $times);
    }

    /**
     * Assert что событие NotificationSent было отправлено
     */
    protected function assertNotificationSent(callable $callback = null): void
    {
        $this->assertEventDispatched(NotificationSent::class, $callback);
    }

    /**
     * Assert что событие NotificationDelivered было отправлено
     */
    protected function assertNotificationDelivered(callable $callback = null): void
    {
        $this->assertEventDispatched(NotificationDelivered::class, $callback);
    }

    /**
     * Assert что событие NotificationFailed было отправлено
     */
    protected function assertNotificationFailed(callable $callback = null): void
    {
        $this->assertEventDispatched(NotificationFailed::class, $callback);
    }
}
