<?php

namespace App\Services\Providers;

use App\Services\CircuitBreakerService;
use Illuminate\Support\Facades\Log;

/**
 * Mock-провайдер для Email уведомлений.
 */
class EmailMockProvider implements NotificationProviderInterface
{
    private const FAILURE_RATE = 0.03;

    public function __construct(
        private CircuitBreakerService $circuitBreaker,
    ) {}

    public function send(string $recipient, string $message): bool
    {
        return $this->circuitBreaker->call($this->getProviderName(), function () use ($recipient, $message) {
            if (rand(1, 100) <= (self::FAILURE_RATE * 100)) {
                Log::warning('Email Mock Provider - simulated failure', [
                    'recipient' => $recipient,
                ]);
                return false;
            }

            return true;
        });
    }

    public function getProviderName(): string
    {
        return 'email_mock';
    }
}
