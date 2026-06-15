<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test Успешная массовая рассылка SMS
     * @covers \App\Http\Controllers\Api\NotificationController::bulk
     */
    public function testBulkSmsNotificationCreated(): void
    {
        $payload = [
            'channel' => 'sms',
            'message' => 'Ваш код подтверждения: 123456',
            'recipients' => ['+79991234567', '+79997654321'],
            'priority' => 'critical',
        ];

        $response = $this->postJson('/api/v1/notifications/bulk', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'channel' => 'sms',
                    'priority' => 'critical',
                ],
                'meta' => [
                    'status' => 'created',
                ],
            ]);
    }

    /**
     * @test Валидация - отсутствие обязательных полей
     * @covers \App\Http\Controllers\Api\NotificationController::bulk
     */
    public function testBulkValidationFailsWithoutRequiredFields(): void
    {
        $response = $this->postJson('/api/v1/notifications/bulk', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channel', 'message', 'recipients']);
    }

    /**
     * @test Валидация - неверный приоритет
     * @covers \App\Http\Controllers\Api\NotificationController::bulk
     */
    public function testBulkValidationFailsWithInvalidPriority(): void
    {
        $payload = [
            'channel' => 'sms',
            'message' => 'Тест',
            'recipients' => ['+79991234567'],
            'priority' => 'invalid-priority',
        ];

        $response = $this->postJson('/api/v1/notifications/bulk', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('priority');
    }

    /**
     * @test Валидация - пустые получатели
     * @covers \App\Http\Controllers\Api\NotificationController::bulk
     */
    public function testBulkValidationFailsWithEmptyRecipients(): void
    {
        $payload = [
            'channel' => 'sms',
            'message' => 'Тест',
            'recipients' => [],
            'priority' => 'normal',
        ];

        $response = $this->postJson('/api/v1/notifications/bulk', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recipients');
    }

    /**
     * @test Получение уведомления по ID
     * @covers \App\Http\Controllers\Api\NotificationController::show
     */
    public function testGetNotificationById(): void
    {
        $notification = Notification::factory()->create([
            'channel' => 'email',
            'message' => 'Тестовое сообщение',
            'priority' => 'critical',
        ]);

        NotificationRecipient::factory()->create([
            'notification_id' => $notification->id,
            'recipient_identifier' => 'test@example.com',
            'status' => Status::SENT->value,
            'attempts' => 1,
        ]);

        $response = $this->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $notification->id,
                    'channel' => 'email',
                    'priority' => 'critical',
                ],
            ]);
    }

    /**
     * @test Получение несуществующего уведомления
     * @covers \App\Http\Controllers\Api\NotificationController::show
     */
    public function testGetNonexistentNotificationReturns404(): void
    {
        $response = $this->getJson('/api/v1/notifications/nonexistent-id');

        $response->assertStatus(404);
    }

    /**
     * @test Получение статуса получателя
     * @covers \App\Http\Controllers\Api\NotificationController::recipientStatus
     */
    public function testGetRecipientStatus(): void
    {
        $notification = Notification::create([
            'idempotency_key' => Str::uuid()->toString(),
            'channel' => 'sms',
            'message' => 'Тест',
            'batch_id' => Str::uuid()->toString(),
            'priority' => 'normal',
        ]);

        $recipient = NotificationRecipient::create([
            'id' => Str::uuid()->toString(),
            'notification_id' => $notification->id,
            'recipient_identifier' => '+79991234567',
            'status' => Status::DELIVERED->value,
            'attempts' => 1,
        ]);

        $response = $this->getJson(
            "/api/v1/notifications/{$notification->id}/recipients/{$recipient->recipient_identifier}"
        );

        $response->assertStatus(200)
            ->assertJson([
                'recipient_identifier' => '+79991234567',
                'status' => 'delivered',
            ]);
    }

    /**
     * @test Получение статуса несуществующего получателя
     * @covers \App\Http\Controllers\Api\NotificationController::recipientStatus
     */
    public function testGetNonexistentRecipientStatusReturns404(): void
    {
        $notification = Notification::create([
            'idempotency_key' => Str::uuid()->toString(),
            'channel' => 'sms',
            'message' => 'Тест',
            'batch_id' => Str::uuid()->toString(),
            'priority' => 'normal',
        ]);

        $response = $this->getJson("/api/v1/notifications/{$notification->id}/recipients/nonexistent");

        $response->assertStatus(404);
    }

    /**
     * @test Получение истории получателя
     * @covers \App\Http\Controllers\Api\NotificationController::recipientHistory
     */
    public function testGetRecipientHistory(): void
    {
        $notification = Notification::create([
            'idempotency_key' => Str::uuid()->toString(),
            'channel' => 'email',
            'message' => 'Тест',
            'batch_id' => Str::uuid()->toString(),
            'priority' => 'normal',
        ]);

        NotificationRecipient::create([
            'id' => Str::uuid()->toString(),
            'notification_id' => $notification->id,
            'recipient_identifier' => 'user@example.com',
            'status' => Status::DELIVERED->value,
        ]);

        $response = $this->getJson('/api/v1/recipients/user@example.com/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'notification_id',
                        'recipient_identifier',
                        'status',
                    ],
                ],
            ]);
    }
}
