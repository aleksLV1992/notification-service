<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Services\CircuitBreakerService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsMockProvider implements NotificationProviderInterface
{
    private const FAILURE_RATE = 0.05;

    public function __construct(
        private readonly CircuitBreakerService $circuitBreaker,
    ) {}

    public function send(string $recipient, string $message): bool
    {
        return $this->circuitBreaker->call($this->getProviderName(), function () use ($recipient) {
            if (rand(1, 100) <= (self::FAILURE_RATE * 100)) {
                Log::warning('SMS mock provider simulated failure', ['recipient' => $recipient]);
                throw new RuntimeException('SMS provider simulated failure');
            }

            return true;
        });
    }

    public function confirmDelivery(string $recipient): bool
    {
        return $this->circuitBreaker->call($this->getProviderName(), function () use ($recipient) {
            Log::info('SMS mock provider delivery confirmed', ['recipient' => $recipient]);

            return true;
        });
    }

    public function getProviderName(): string
    {
        return 'sms_mock';
    }
}
