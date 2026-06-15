<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для добавления Correlation ID.
 * Используется для трассировки запросов через все сервисы.
 */
class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем Correlation ID из заголовка или генерируем новый
        $correlationId = $request->header('X-Correlation-ID')
            ?? $request->header('X-Request-ID')
            ?? Str::uuid()->toString();

        // Добавляем в атрибуты запроса
        $request->attributes->set('correlation_id', $correlationId);

        // Добавляем в контекст логгера
        Log::shareContext([
            'correlation_id' => $correlationId,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
        ]);

        // Выполняем запрос
        $response = $next($request);

        // Добавляем Correlation ID в ответ
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
