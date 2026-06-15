<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\NotificationRecipient;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\Providers\NotificationProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class RabbitMqIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! filter_var(env('RABBITMQ_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('RabbitMQ integration tests are disabled.');
        }

        parent::setUp();
        config(['queue.default' => 'rabbitmq']);
    }

    public function test_notification_delivered_through_rabbitmq_queue(): void
    {
        $provider = Mockery::mock(NotificationProviderInterface::class);
        $provider->shouldReceive('send')->andReturn(true);
        $provider->shouldReceive('confirmDelivery')->andReturn(true);
        $provider->shouldReceive('getProviderName')->andReturn('sms_mock');

        app(NotificationProviderFactory::class)->register('sms', $provider);

        $response = $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'RabbitMQ E2E',
            'recipients' => ['+79994445566'],
            'priority' => 'critical',
        ]);

        $response->assertStatus(201);

        $recipient = NotificationRecipient::query()->first();
        $this->assertSame(Status::QUEUED, $recipient->status);

        Artisan::call('queue:work', [
            'connection' => 'rabbitmq',
            '--once' => true,
            '--queue' => 'critical,default,marketing',
            '--tries' => 3,
        ]);

        $this->assertSame(Status::DELIVERED, $recipient->fresh()->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
