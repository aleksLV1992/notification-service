<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interfaces\MetricsInterface;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __construct(
        private readonly MetricsInterface $metrics,
    ) {}

    public function __invoke(): Response
    {
        return response(
            content: $this->metrics->getMetrics(),
            status: 200,
            headers: ['Content-Type' => 'text/plain'],
        );
    }
}
