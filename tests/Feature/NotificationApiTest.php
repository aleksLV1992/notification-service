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

    public function test_bulk_sms_notification_created(): void
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

    public function test_bulk_validation_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/notifications/bulk', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_bulk_validation_fails_with_invalid_priority(): void
    {
        $payload = [
            'channel' => 'sms',
            'message' => 'Тест',
            'recipients' => ['+79991234567'],
            'priority' => 'invalid-priority',
        ];

        $response = $this->postJson('/api/v1/notifications/bulk', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_bulk_validation_fails_with_empty_recipients(): void
    {
        $payload = [
            'channel' => 'sms',
            'message' => 'Тест',
            'recipients' => [],
            'priority' => 'normal',
        ];

        $response = $this->postJson('/api/v1/notifications/bulk', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_get_notification_by_id(): void
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

    public function test_get_nonexistent_notification_returns404(): void
    {
        $response = $this->getJson('/api/v1/notifications/nonexistent-id');

        $response->assertStatus(404);
    }

    public function test_get_recipient_status(): void
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

    public function test_get_nonexistent_recipient_status_returns404(): void
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

    public function test_get_recipient_history(): void
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
