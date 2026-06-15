<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulkNotificationRequest;
use App\Http\Resources\NotificationRecipientResource;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function bulk(StoreBulkNotificationRequest $request): JsonResponse
    {
        $result = $this->notificationService->sendBulkNotification($request->toDto());

        if ($result->isDuplicate) {
            return response()->json([
                'message' => $result->message,
                'notification_id' => $result->isProcessing ? null : $result->notification->id,
                'meta' => ['status' => $result->isProcessing ? 'processing' : 'duplicate'],
            ], 409);
        }

        if ($result->isRateLimitExceeded) {
            return response()->json([
                'message' => $result->message,
            ], 429);
        }

        return (new NotificationResource($result->notification))
            ->additional(['meta' => ['status' => 'created']])
            ->response()
            ->setStatusCode(201);
    }

    public function recipientStatus(string $notificationId, string $recipientIdentifier): JsonResponse
    {
        $status = $this->notificationService->getNotificationStatus(
            notificationId: $notificationId,
            recipientIdentifier: $recipientIdentifier,
        );

        if ($status === null) {
            return ApiResponse::notFound('Recipient status not found');
        }

        return response()->json($status);
    }

    public function recipientHistory(string $recipientIdentifier, Request $request): JsonResponse
    {
        $history = $this->notificationService->getRecipientHistory(
            recipientIdentifier: $recipientIdentifier,
            limit: (int) $request->query('limit', 15),
        );

        return response()->json([
            'data' => NotificationRecipientResource::collection($history),
            'current_page' => $history->currentPage(),
            'last_page' => $history->lastPage(),
            'total' => $history->total(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $notification = $this->notificationService->getNotificationById($id);

        if ($notification === null) {
            return ApiResponse::notFound('Notification not found');
        }

        return (new NotificationResource($notification))
            ->response()
            ->setStatusCode(200);
    }
}
