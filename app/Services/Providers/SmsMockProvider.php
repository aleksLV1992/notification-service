<?php

namespace App\Services\Providers;

use App\Services\CircuitBreakerService;

/**
 * Mock-провайдер для SMS уведомлений.
 * Имитирует работу реального SMS шлюза.
 */
class SmsMockProvider implements NotificationProviderInterface
{
    private const FAILURE_RATE = 0.05; // 5% шанс неудачи для тестирования retry

    public function __construct(
        private CircuitBreakerService $circuitBreaker,
    ) {}

    public function send(string $recipient, string $message): bool
    {
        return $this->circuitBreaker->call($this->getProviderName(), function () use ($recipient, $message) {
            // Имитация случайных неудач для тестирования retry-механизма
            if (rand(1, 100) <= (self::FAILURE_RATE * 100)) {
                Log::warning('SMS Mock Provider - simulated failure', [
                    'recipient' => $recipient,
                ]);
                return false;
            }

            return true;
        });
    }

    public function getProviderName(): string
    {
        return 'sms_mock';
    }
}
