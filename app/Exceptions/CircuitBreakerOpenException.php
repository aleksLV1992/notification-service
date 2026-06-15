<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly int $retryAfter,
    ) {
        parent::__construct("Circuit breaker is open for provider: {$provider}. Retry after {$retryAfter} seconds.");
    }
}
