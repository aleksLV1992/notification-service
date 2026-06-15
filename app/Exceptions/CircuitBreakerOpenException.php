<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Исключение, выбрасываемое когда Circuit Breaker открыт.
 */
class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(string $provider, int $retryAfter)
    {
        parent::__construct("Circuit breaker is open for provider: {$provider}. Retry after {$retryAfter} seconds.");
    }
}
