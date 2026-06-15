<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis as RedisFacade;

class HealthController extends Controller
{
    /**
     * Проверка здоровья всех сервисов.
     */
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

    /**
     * Проверка только базы данных.
     */
    public function database(): JsonResponse
    {
        $healthy = $this->checkDatabase();

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'service' => 'database',
        ], $healthy ? 200 : 503);
    }

    /**
     * Проверка только Redis.
     */
    public function redis(): JsonResponse
    {
        $healthy = $this->checkRedis();

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'service' => 'redis',
        ], $healthy ? 200 : 503);
    }

    /**
     * Проверка только RabbitMQ.
     */
    public function rabbitmq(): JsonResponse
    {
        $healthy = $this->checkRabbitMQ();

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'service' => 'rabbitmq',
        ], $healthy ? 200 : 503);
    }

    /**
     * Проверка базы данных.
     */
    private function checkDatabase(): array
    {
        try {
            $pdo = DB::connection()->getPdo();
            $result = DB::select('SELECT 1');

            return [
                'status' => 'healthy',
                'latency_ms' => $this->measureLatency(fn() => DB::select('SELECT 1')),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Проверка Redis.
     */
    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            $result = RedisFacade::ping();
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => $result === '+PONG' ? 'healthy' : 'unhealthy',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Проверка RabbitMQ через Management API.
     */
    private function checkRabbitMQ(): array
    {
        try {
            $host = config('queue.connections.rabbitmq.hosts.0.host', 'rabbitmq');
            $port = config('queue.connections.rabbitmq.hosts.0.port', 15672);
            $user = config('queue.connections.rabbitmq.user', 'guest');
            $password = config('queue.connections.rabbitmq.password', 'guest');

            $start = microtime(true);
            $response = Http::withBasicAuth($user, $password)
                ->timeout(5)
                ->get("http://{$host}:{$port}/api/health/checks/local-alarms");

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
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Проверка всех сервисов.
     */
    private function allHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                return false;
            }
        }
        return true;
    }

    /**
     * Измерение времени выполнения в мс.
     */
    private function measureLatency(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return round((microtime(true) - $start) * 1000, 2);
    }
}
