<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Notification;

use App\Actions\Notification\SendNotificationAction;
use App\Enums\Priority;
use App\Enums\Status;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Services\Providers\NotificationProviderInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Actions\Notification\SendNotificationAction
 */
class SendNotificationActionTest extends TestCase
{
    private MockInterface $recipientRepo;
    private MockInterface $provider;
    private SendNotificationAction $action;

    public function setUp(): void
    {
        parent::setUp();

        $this->recipientRepo = Mockery::mock(NotificationRecipientRepositoryInterface::class);
        $this->provider = Mockery::mock(NotificationProviderInterface::class);
        $this->action = new SendNotificationAction(
            $this->recipientRepo,
            $this->provider
        );
    }

    /**
     * @test Успешная отправка уведомления
     * @covers ::execute
     */
    public function testExecuteSuccess(): void
    {
        // Arrange
        $notification = Notification::factory()->create([
            'channel' => 'sms',
            'priority' => Priority::CRITICAL,
        ]);

        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => '+79991234567',
            'status' => Status::QUEUED,
        ]);

        $this->provider
            ->shouldReceive('send')
            ->with('+79991234567', 'Test message')
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
            Priority::CRITICAL,
            $notification->id
        );

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test Неудачная отправка уведомления
     * @covers ::execute
     */
    public function testExecuteFailure(): void
    {
        // Arrange
        $notification = Notification::factory()->create([
            'channel' => 'sms',
            'priority' => Priority::NORMAL,
        ]);

        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => '+79991234567',
            'status' => Status::QUEUED,
        ]);

        $this->provider
            ->shouldReceive('send')
            ->with('+79991234567', 'Test message')
            ->once()
            ->andReturn(false);

        $this->recipientRepo
            ->shouldReceive('markAsSent')
            ->with($recipient->id)
            ->once();

        // Act
        $result = $this->action->execute(
            $recipient,
            'Test message',
            Priority::NORMAL,
            $notification->id
        );

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test Обработка неудачи отправки
     * @covers ::handleFailure
     */
    public function testHandleFailure(): void
    {
        // Arrange
        $notification = Notification::factory()->create();
        $recipient = NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => '+79991234567',
        ]);

        $this->recipientRepo
            ->shouldReceive('markAsFailed')
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
