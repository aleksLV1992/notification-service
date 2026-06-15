<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use stdClass;

final class ApiResponse
{
    public static function emptyDetails(): stdClass
    {
        return new stdClass;
    }

    public static function error(
        string $code,
        string $message,
        int $status,
        array|object|null $details = null,
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details ?? self::emptyDetails(),
            ],
        ], $status);
    }

    public static function notFound(string $message): JsonResponse
    {
        return self::error('NOT_FOUND', $message, 404);
    }

    public static function validationFailed(array $details): JsonResponse
    {
        return self::error('VALIDATION_ERROR', 'Validation failed', 422, $details);
    }
}
