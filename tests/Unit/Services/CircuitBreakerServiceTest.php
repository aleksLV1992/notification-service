<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CircuitBreakerOpenException;
use App\Services\CircuitBreakerService;
use App\Services\Interfaces\CacheInterface;
use App\Services\Interfaces\MetricsInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CircuitBreakerServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $cache;

    private MockInterface $metrics;

    private CircuitBreakerService $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(CacheInterface::class);
        $this->metrics = Mockery::mock(MetricsInterface::class);
        $this->circuitBreaker = new CircuitBreakerService($this->cache, $this->metrics);
    }

    public function test_call_success_in_closed_state(): void
    {
        // Arrange
        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:state:test_provider')
            ->andReturn('closed');

        $this->cache
            ->shouldReceive('set')
            ->with('circuit_breaker:failures:test_provider', '0');

        // Act
        $result = $this->circuitBreaker->call('test_provider', function () {
            return 'success';
        });

        // Assert
        $this->assertEquals('success', $result);
    }

    public function test_call_rejected_in_open_state(): void
    {
        // Arrange
        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:state:test_provider')
            ->andReturn('open');

        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:last_failure:test_provider')
            ->andReturn((string) time());

        $this->metrics
            ->shouldReceive('incrementCircuitBreakerTriggered')
            ->with('test_provider')
            ->once();

        // Assert
        $this->expectException(CircuitBreakerOpenException::class);

        // Act
        $this->circuitBreaker->call('test_provider', function () {
            return 'should not be called';
        });
    }

    public function test_transition_to_open_after_failure_threshold(): void
    {
        // Arrange - 4 ошибки (еще не порог)
        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:state:test_provider')
            ->andReturn('closed');

        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:failures:test_provider')
            ->andReturn('4');

        $this->cache
            ->shouldReceive('set')
            ->with('circuit_breaker:failures:test_provider', '5');

        $this->cache
            ->shouldReceive('set')
            ->with('circuit_breaker:state:test_provider', 'open');

        $this->cache
            ->shouldReceive('set')
            ->with('circuit_breaker:last_failure:test_provider', Mockery::any());

        // Assert
        $this->expectException(\Exception::class);

        // Act - 5-я ошибка должна перевести в OPEN
        $this->circuitBreaker->call('test_provider', function () {
            throw new \Exception('Test error');
        });
    }

    public function test_transition_to_closed_after_success_in_half_open(): void
    {
        // Arrange
        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:state:test_provider')
            ->andReturn('half_open');

        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:successes:test_provider')
            ->andReturn('1'); // Уже 1 успех, нужно 2 для перехода

        $this->cache
            ->shouldReceive('set')
            ->with('circuit_breaker:successes:test_provider', '2');

        $this->cache
            ->shouldReceive('set')
            ->with('circuit_breaker:state:test_provider', 'closed');

        $this->cache
            ->shouldReceive('set')
            ->with('circuit_breaker:failures:test_provider', '0');

        // Act
        $result = $this->circuitBreaker->call('test_provider', function () {
            return 'success';
        });

        // Assert
        $this->assertEquals('success', $result);
    }

    public function test_get_stats(): void
    {
        // Arrange
        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:state:test_provider')
            ->andReturn('closed');

        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:failures:test_provider')
            ->andReturn('2');

        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:successes:test_provider')
            ->andReturn('5');

        $this->cache
            ->shouldReceive('get')
            ->with('circuit_breaker:last_failure:test_provider')
            ->andReturn('0');

        // Act
        $stats = $this->circuitBreaker->getStats('test_provider');

        // Assert
        $this->assertEquals([
            'state' => 'closed',
            'failure_count' => 2,
            'success_count' => 5,
            'last_failure_time' => 0,
            'retry_after' => 0,
        ], $stats);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
