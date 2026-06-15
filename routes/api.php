<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/database', [HealthController::class, 'database']);
Route::get('/health/redis', [HealthController::class, 'redis']);
Route::get('/health/rabbitmq', [HealthController::class, 'rabbitmq']);
Route::get('/metrics', MetricsController::class);

Route::middleware(['api'])->prefix('v1')->group(function (): void {
    Route::post('/notifications/bulk', [NotificationController::class, 'bulk']);
    Route::get('/notifications/{notificationId}/recipients/{recipientIdentifier}', [NotificationController::class, 'recipientStatus']);
    Route::get('/recipients/{recipientIdentifier}/history', [NotificationController::class, 'recipientHistory']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
});
