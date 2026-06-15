<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationQueuePrioritizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_jobs_dispatched_to_critical_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Critical priority',
            'recipients' => ['+79990000001'],
            'priority' => 'critical',
        ])->assertStatus(201);

        Queue::assertPushedOn('critical', SendNotificationJob::class);
    }

    public function test_normal_jobs_dispatched_to_default_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Normal priority',
            'recipients' => ['+79990000002'],
            'priority' => 'normal',
        ])->assertStatus(201);

        Queue::assertPushedOn('default', SendNotificationJob::class);
    }

    public function test_marketing_jobs_dispatched_to_marketing_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Marketing priority',
            'recipients' => ['+79990000003'],
            'priority' => 'marketing',
        ])->assertStatus(201);

        Queue::assertPushedOn('marketing', SendNotificationJob::class);
    }
}
