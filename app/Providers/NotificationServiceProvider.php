<?php

namespace App\Providers;

use App\Events\NotificationDelivered;
use App\Events\NotificationFailed;
use App\Events\NotificationSent;
use App\Events\Subscribers\NotificationEventSubscriber;
use App\Models\Notification as EloquentNotification;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Repositories\NotificationRecipientRepository;
use App\Repositories\NotificationRepository;
use App\Services\CircuitBreakerService;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\MetricsInterface;
use App\Services\Interfaces\RedisClientInterface;
use App\Services\Metrics\MetricsService;
use App\Services\NotificationService;
use App\Services\Providers\EmailMockProvider;
use App\Services\Providers\SmsMockProvider;
use App\Services\RateLimiter\DeduplicationService;
use App\Services\RateLimiter\RateLimiterService;
use App\Services\Redis\RedisCacheService;
use App\Services\Redis\RedisClientService;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Redis::class, function ($app) {
            $redis = new \Redis();
            $redis->connect(
                config('database.redis.default.host'),
                config('database.redis.default.port')
            );
            return $redis;
        });

        $this->app->singleton(RedisClientInterface::class, RedisClientService::class);
        $this->app->singleton(CacheInterface::class, RedisCacheService::class);
        $this->app->singleton(CircuitBreakerService::class);
        $this->app->singleton(RateLimiterService::class);
        $this->app->singleton(MetricsInterface::class, MetricsService::class);
        $this->app->singleton(NotificationRecipientRepositoryInterface::class, NotificationRecipientRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, function ($app) {
            return new NotificationRepository(
                $app->make(EloquentNotification::class),
                $app->make(NotificationRecipientRepositoryInterface::class)
            );
        });
        $this->app->singleton(DeduplicationService::class);

        $this->app->singleton(SmsMockProvider::class, function ($app) {
            return new SmsMockProvider(
                $app->make(CircuitBreakerService::class)
            );
        });

        $this->app->singleton(EmailMockProvider::class, function ($app) {
            return new EmailMockProvider(
                $app->make(CircuitBreakerService::class)
            );
        });

        $this->app->singleton(NotificationProviderFactory::class, function ($app) {
            $factory = new NotificationProviderFactory();
            $factory->register('sms', $app->make(SmsMockProvider::class));
            $factory->register('email', $app->make(EmailMockProvider::class));
            return $factory;
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(NotificationRepositoryInterface::class),
                $app->make(NotificationRecipientRepositoryInterface::class),
                $app->make(DeduplicationService::class),
                $app->make(NotificationProviderFactory::class),
                $app->make(RateLimiterService::class),
                $app->make(MetricsInterface::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $events = $this->app->make('events');
        $events->subscribe(NotificationEventSubscriber::class);
    }
}
