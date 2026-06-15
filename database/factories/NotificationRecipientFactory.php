<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationRecipient>
 */
class NotificationRecipientFactory extends Factory
{
    protected $model = NotificationRecipient::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'notification_id' => Notification::factory(),
            'recipient_identifier' => fake()->phoneNumber(),
            'status' => Status::QUEUED,
            'error_message' => null,
            'attempts' => 0,
            'sent_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::SENT,
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function failed(string $errorMessage = 'Test error'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::FAILED,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    public function forNotification(Notification $notification): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_id' => $notification->id,
        ]);
    }
}
