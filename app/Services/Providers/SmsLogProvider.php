<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Services\CircuitBreakerService;
use Illuminate\Support\Facades\Log;

class SmsLogProvider implements NotificationProviderInterface
{
    private const LOG_CHANNEL = 'notifications';

    public function __construct(
        private readonly CircuitBreakerService $circuitBreaker,
    ) {}

    public function send(string $recipient, string $message): bool
    {
        return $this->circuitBreaker->call($this->getProviderName(), function () use ($recipient, $message): bool {
            Log::channel(self::LOG_CHANNEL)->info('SMS sent', [
                'recipient' => $recipient,
                'message' => $message,
            ]);

            return true;
        });
    }

    public function confirmDelivery(string $recipient): bool
    {
        return $this->circuitBreaker->call($this->getProviderName(), function () use ($recipient): bool {
            Log::channel(self::LOG_CHANNEL)->info('SMS delivery confirmed', [
                'recipient' => $recipient,
            ]);

            return true;
        });
    }

    public function getProviderName(): string
    {
        return 'sms_log';
    }
}
