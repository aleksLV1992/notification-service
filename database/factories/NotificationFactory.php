<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\Priority;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'channel' => Channel::SMS,
            'message' => fake()->sentence(),
            'batch_id' => null,
            'priority' => Priority::NORMAL,
            'metadata' => null,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Priority::CRITICAL,
        ]);
    }

    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Priority::MARKETING,
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Channel::EMAIL,
        ]);
    }
}
