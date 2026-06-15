<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Notification;

use App\Actions\Notification\SendNotificationAction;
use App\Enums\Priority;
use App\Enums\Status;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\Providers\NotificationProviderInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SendNotificationActionTest extends TestCase
{
    private MockInterface $recipientRepo;

    private MockInterface $providerFactory;

    private MockInterface $provider;

    private SendNotificationAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipientRepo = Mockery::mock(NotificationRecipientRepositoryInterface::class);
        $this->providerFactory = Mockery::mock(NotificationProviderFactory::class);
        $this->provider = Mockery::mock(NotificationProviderInterface::class);
        $this->action = new SendNotificationAction(
            $this->recipientRepo,
            $this->providerFactory
        );
    }

    public function test_execute_success(): void
    {
        // Arrange
        $notification = Notification::factory()->create([
            'channel' => 'sms',
            'priority' => Priority::CRITICAL,
        ]);

        $recipient = NotificationRecipient::factory()
            ->for($notification)
            ->create([
                'recipient_identifier' => '+79991234567',
                'status' => Status::QUEUED,
            ]);

        $recipient->load('notification');

        $this->providerFactory
            ->shouldReceive('get')
            ->with('sms')
            ->twice()
            ->andReturn($this->provider);

        $this->provider
            ->shouldReceive('send')
            ->with('+79991234567', 'Test message')
            ->once()
            ->andReturn(true);

        $this->provider
            ->shouldReceive('confirmDelivery')
            ->with('+79991234567')
            ->once()
            ->andReturn(true);

        $this->recipientRepo
            ->shouldReceive('markAsSent')
            ->with($recipient->id)
            ->once();

        $this->recipientRepo
            ->shouldReceive('markAsDelivered')
            ->with($recipient->id)
            ->once();

        // Act
        $result = $this->action->execute(
            $recipient,
            'Test message',
            $notification->id
        );

        // Assert
        $this->assertTrue($result);
    }

    public function test_execute_failure(): void
    {
        // Arrange
        $notification = Notification::factory()->create([
            'channel' => 'sms',
            'priority' => Priority::NORMAL,
        ]);

        $recipient = NotificationRecipient::factory()
            ->for($notification)
            ->create([
                'recipient_identifier' => '+79991234567',
                'status' => Status::QUEUED,
            ]);

        $recipient->load('notification');

        $this->providerFactory
            ->shouldReceive('get')
            ->with('sms')
            ->once()
            ->andReturn($this->provider);

        $this->provider
            ->shouldReceive('send')
            ->with('+79991234567', 'Test message')
            ->once()
            ->andReturn(false);

        $this->recipientRepo
            ->shouldNotReceive('markAsSent');

        // Act
        $result = $this->action->execute(
            $recipient,
            'Test message',
            $notification->id
        );

        // Assert
        $this->assertFalse($result);
    }

    public function test_handle_failure(): void
    {
        // Arrange
        $notification = Notification::factory()->create();
        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => '+79991234567',
        ]);

        $this->recipientRepo
            ->shouldReceive('markAsDropped')
            ->with($recipient->id, 'Connection timeout')
            ->once();

        // Act
        $this->action->handleFailure($recipient, 'Connection timeout');

        // Assert
        $this->assertTrue(true); // Если не упало - тест пройден
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
