<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Subscribers\NotificationEventSubscriber;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Repositories\NotificationRecipientRepository;
use App\Repositories\NotificationRepository;
use App\Services\CircuitBreakerService;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\Factories\NotificationProviderResolver;
use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\MetricsInterface;
use App\Services\Interfaces\RedisClientInterface;
use App\Services\Metrics\MetricsService;
use App\Services\NotificationService;
use App\Services\Providers\EmailLogProvider;
use App\Services\Providers\EmailMockProvider;
use App\Services\Providers\SmsLogProvider;
use App\Services\Providers\SmsMockProvider;
use App\Services\RateLimiter\DeduplicationService;
use App\Services\RateLimiter\RateLimiterService;
use App\Services\Redis\RedisCacheService;
use App\Services\Redis\RedisClientService;
use Illuminate\Support\ServiceProvider;
use Redis;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Redis::class, function (): Redis {
            $redis = new Redis;
            $redis->connect(
                (string) config('database.redis.default.host'),
                (int) config('database.redis.default.port'),
            );

            return $redis;
        });

        $this->app->singleton(RedisClientInterface::class, RedisClientService::class);
        $this->app->singleton(CacheInterface::class, RedisCacheService::class);
        $this->app->singleton(MetricsInterface::class, MetricsService::class);
        $this->app->singleton(CircuitBreakerService::class);
        $this->app->singleton(RateLimiterService::class);
        $this->app->singleton(DeduplicationService::class);
        $this->app->singleton(NotificationRecipientRepositoryInterface::class, NotificationRecipientRepository::class);
        $this->app->singleton(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->singleton(NotificationService::class);

        $this->app->singleton(SmsMockProvider::class);
        $this->app->singleton(SmsLogProvider::class);
        $this->app->singleton(EmailMockProvider::class);
        $this->app->singleton(EmailLogProvider::class);
        $this->app->singleton(NotificationProviderResolver::class);

        $this->app->singleton(NotificationProviderFactory::class, function ($app): NotificationProviderFactory {
            $resolver = $app->make(NotificationProviderResolver::class);
            $factory = new NotificationProviderFactory;
            $factory->register('sms', $resolver->resolve('sms'));
            $factory->register('email', $resolver->resolve('email'));

            return $factory;
        });
    }

    public function boot(): void
    {
        $this->app->make('events')->subscribe(NotificationEventSubscriber::class);
    }
}
