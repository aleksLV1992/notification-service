<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Notification as EloquentNotification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EloquentNotification::class, function () {
            return new EloquentNotification;
        });
    }

    public function boot(): void
    {
        //
    }
}
