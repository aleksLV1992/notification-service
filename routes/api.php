<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health checks
Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/database', [HealthController::class, 'database']);
Route::get('/health/redis', [HealthController::class, 'redis']);
Route::get('/health/rabbitmq', [HealthController::class, 'rabbitmq']);

// Metrics endpoint for Prometheus
Route::get('/metrics', function () {
    $metricsService = app(\App\Services\MetricsService::class);
    return response($metricsService->getMetrics(), 200, ['Content-Type' => 'text/plain']);
});

Route::middleware(['api'])->prefix('v1')->group(function () {
    // Массовая рассылка уведомлений
    Route::post('/notifications/bulk', [NotificationController::class, 'bulk']);

    // Получение статуса уведомления для конкретного получателя
    Route::get('/notifications/{notificationId}/recipients/{recipientIdentifier}', [NotificationController::class, 'recipientStatus']);

    // История уведомлений для получателя
    Route::get('/recipients/{recipientIdentifier}/history', [NotificationController::class, 'recipientHistory']);

    // Получение информации об уведомлении
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
});
