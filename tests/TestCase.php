<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Interfaces\CacheInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\ArrayCacheService;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(CacheInterface::class, ArrayCacheService::class);
        config(['queue.default' => 'sync']);
    }
}
