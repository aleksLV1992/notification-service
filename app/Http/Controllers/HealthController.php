<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis as RedisFacade;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'rabbitmq' => $this->checkRabbitMQ(),
        ];

        $healthy = $this->allHealthy($checks);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    public function database(): JsonResponse
    {
        $check = $this->checkDatabase();
        $healthy = $check['status'] === 'healthy';

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'service' => 'database',
        ], $healthy ? 200 : 503);
    }

    public function redis(): JsonResponse
    {
        $check = $this->checkRedis();
        $healthy = $check['status'] === 'healthy';

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'service' => 'redis',
        ], $healthy ? 200 : 503);
    }

    public function rabbitmq(): JsonResponse
    {
        $check = $this->checkRabbitMQ();
        $healthy = $check['status'] === 'healthy';

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'service' => 'rabbitmq',
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            return [
                'status' => 'healthy',
                'latency_ms' => $this->measureLatency(fn () => DB::select('SELECT 1')),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            $result = RedisFacade::ping();
            $latency = (microtime(true) - $start) * 1000;
            $healthy = $result === true || $result === '+PONG' || $result === 'PONG';

            return [
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'latency_ms' => round($latency, 2),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRabbitMQ(): array
    {
        try {
            $host = config('queue.connections.rabbitmq.hosts.0.host', 'rabbitmq');
            $user = config('queue.connections.rabbitmq.hosts.0.user', 'guest');
            $password = config('queue.connections.rabbitmq.hosts.0.password', 'guest');
            $managementPort = (int) env('RABBITMQ_MANAGEMENT_PORT', 15672);

            $start = microtime(true);
            $response = Http::withBasicAuth($user, $password)
                ->timeout(5)
                ->get("http://{$host}:{$managementPort}/api/health/checks/local-alarms");

            $latency = (microtime(true) - $start) * 1000;

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'latency_ms' => round($latency, 2),
                ];
            }

            return [
                'status' => 'unhealthy',
                'error' => 'RabbitMQ health check failed',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function allHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                return false;
            }
        }

        return true;
    }

    private function measureLatency(callable $callback): float
    {
        $start = microtime(true);
        $callback();

        return round((microtime(true) - $start) * 1000, 2);
    }
}
