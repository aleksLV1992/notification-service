<?php

declare(strict_types=1);

/**
 * Скрипт проверок бизнес-логики через контейнер Laravel.
 * Запуск: docker-compose exec app php scripts/tinker-checks.php
 */

use App\DTO\BulkNotificationData;
use App\Enums\Status;
use App\Exceptions\CircuitBreakerOpenException;
use App\Models\NotificationRecipient;
use App\Services\CircuitBreakerService;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\Interfaces\CacheInterface;
use App\Services\NotificationService;
use App\Services\Providers\NotificationProviderInterface;
use App\Services\RateLimiter\DeduplicationService;
use App\Services\RateLimiter\RateLimiterService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['queue.default' => 'sync']);

$passed = 0;
$failed = 0;

function check(string $name, bool $result, string $details = ''): void
{
    global $passed, $failed;
    if ($result) {
        $passed++;
        echo "[OK] {$name}".($details ? " — {$details}" : '').PHP_EOL;
    } else {
        $failed++;
        echo "[FAIL] {$name}".($details ? " — {$details}" : '').PHP_EOL;
    }
}

echo '=== Notification Service Tinker Checks ==='.PHP_EOL;

// 1. Provider factory (Registry pattern)
$factory = app(NotificationProviderFactory::class);
check('Factory supports sms', $factory->supports('sms'));
check('Factory supports email', $factory->supports('email'));
check('Factory rejects unknown channel', ! $factory->supports('push'));

// 2. Deduplication
$dedup = app(DeduplicationService::class);
$key = (string) Str::uuid();
check('Dedup: new key is not duplicate', ! $dedup->isDuplicate($key));
$dedup->markAsProcessed($key, 'notif-1');
check('Dedup: same key is duplicate', $dedup->isDuplicate($key));
check('Dedup: returns notification id', $dedup->getNotificationId($key) === 'notif-1');

// 3. Rate limiter — check without premature increment
$cache = app(CacheInterface::class);
$rateLimiter = new RateLimiterService($cache);
$recipient = '+7999'.rand(1000000, 9999999);
check('Rate limit: first check passes', ! $rateLimiter->exceedsMarketingLimit($recipient));
$rateLimiter->recordMarketingSend($recipient);
$marketingLimit = (int) config('notification.rate_limiter.marketing_limit', 100);
check(
    'Rate limit: count incremented after record',
    $rateLimiter->getRemainingLimit($recipient, 'marketing') === $marketingLimit - 1
);

// 4. Circuit breaker opens on failures
$circuitBreaker = app(CircuitBreakerService::class);
$providerName = 'tinker_test_provider_'.rand(1000, 9999);
$opened = false;
for ($i = 0; $i < 6; $i++) {
    try {
        $circuitBreaker->call($providerName, fn () => throw new RuntimeException('fail'));
    } catch (CircuitBreakerOpenException) {
        $opened = true;
        break;
    } catch (RuntimeException) {
        // expected provider failure
    }
}
check('Circuit breaker opens after threshold', $opened, 'state=open');

// 5. Full delivery chain with mocked provider
$mockProvider = Mockery::mock(NotificationProviderInterface::class);
$mockProvider->shouldReceive('send')->andReturn(true);
$mockProvider->shouldReceive('confirmDelivery')->andReturn(true);
$mockProvider->shouldReceive('getProviderName')->andReturn('tinker_mock');
$factory->register('sms', $mockProvider);

$service = app(NotificationService::class);
$result = $service->sendBulkNotification(BulkNotificationData::from([
    'channel' => 'sms',
    'message' => 'Tinker test',
    'recipients' => ['+79990001122'],
    'priority' => 'critical',
    'idempotency_key' => (string) Str::uuid(),
]));

$recipient = NotificationRecipient::query()
    ->where('notification_id', $result->notification->id)
    ->first();

check('Bulk notification accepted', ! $result->isDuplicate && ! $result->isRateLimitExceeded);
check('Recipient status is delivered', $recipient?->status === Status::DELIVERED, $recipient?->status->value ?? 'null');
check('Recipient has sent_at', $recipient?->sent_at !== null);
check('Recipient has delivered_at', $recipient?->delivered_at !== null);

// 6. Duplicate request
$dupKey = (string) Str::uuid();
$payload = BulkNotificationData::from([
    'channel' => 'sms',
    'message' => 'Dup test',
    'recipients' => ['+79990003344'],
    'priority' => 'normal',
    'idempotency_key' => $dupKey,
]);
$service->sendBulkNotification($payload);
$duplicate = $service->sendBulkNotification($payload);
check('Duplicate request detected', $duplicate->isDuplicate);

// 7. Status API via service
$status = $service->getNotificationStatus(
    $result->notification->id,
    '+79990001122'
);
check('Recipient status query works', $status !== null && $status->status === Status::DELIVERED);

echo PHP_EOL."Result: {$passed} passed, {$failed} failed".PHP_EOL;
exit($failed > 0 ? 1 : 0);
