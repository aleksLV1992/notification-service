<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulkNotificationRequest;
use App\Http\Resources\NotificationRecipientResource;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="Notification Service API",
 *     version="1.0.0",
 *     description="Микросервис уведомлений - массовая рассылка SMS и Email"
 * )
 */
class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/bulk",
     *     summary="Массовая рассылка уведомлений",
     *     tags={"Notifications"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"channel", "message", "recipients"},
     *             @OA\Property(property="channel", type="string", enum={"sms", "email"}, example="sms"),
     *             @OA\Property(property="message", type="string", example="Ваш код подтверждения: 123456"),
     *             @OA\Property(property="recipients", type="array", @OA\Items(type="string"), example={"+79991234567", "+79997654321"}),
     *             @OA\Property(property="priority", type="string", enum={"critical", "normal", "marketing"}, example="critical"),
     *             @OA\Property(property="idempotency_key", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="metadata", type="object", example={"campaign_id": "123"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201, description="Успешное создание рассылки",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440001"),
     *             @OA\Property(property="idempotency_key", type="string"),
     *             @OA\Property(property="channel", type="string"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="batch_id", type="string"),
     *             @OA\Property(property="priority", type="string"),
     *             @OA\Property(property="recipients_count", type="integer"),
     *             @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Некорректный запрос"),
     *     @OA\Response(response=409, description="Дубликат запроса (idempotency)"),
     *     @OA\Response(response=429, description="Превышен лимит отправки")
     * )
     */
    public function bulk(StoreBulkNotificationRequest $request): JsonResponse
    {
        $data = $request->toDto();

        $result = $this->notificationService->sendBulkNotification($data);

        if ($result->isDuplicate) {
            return response()->json([
                'message' => $result->message,
                'notification_id' => $result->notification->id,
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

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/{notificationId}/recipients/{recipientIdentifier}",
     *     summary="Получить статус уведомления для конкретного получателя",
     *     tags={"Notifications"},
     *     @OA\Parameter(
     *         name="notificationId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="recipientIdentifier",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статус уведомления",
     *         @OA\JsonContent(
     *             @OA\Property(property="notification_id", type="string"),
     *             @OA\Property(property="recipient_identifier", type="string"),
     *             @OA\Property(property="channel", type="string"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="priority", type="string"),
     *             @OA\Property(property="status", type="string", enum={"queued", "sent", "delivered", "failed"}),
     *             @OA\Property(property="attempts", type="integer"),
     *             @OA\Property(property="sent_at", type="string", format="date-time"),
     *             @OA\Property(property="delivered_at", type="string", format="date-time"),
     *             @OA\Property(property="failed_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Уведомление или получатель не найдены")
     * )
     */
    public function recipientStatus(string $notificationId, string $recipientIdentifier): JsonResponse
    {
        $status = $this->notificationService->getNotificationStatus(
            notificationId: $notificationId,
            recipientIdentifier: $recipientIdentifier,
        );

        if (!$status) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($status);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/recipients/{recipientIdentifier}/history",
     *     summary="История уведомлений для получателя",
     *     tags={"Notifications"},
     *     @OA\Parameter(
     *         name="recipientIdentifier",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="История уведомлений",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="notification_id", type="string"),
     *                 @OA\Property(property="recipient_identifier", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="channel", type="string"),
     *                 @OA\Property(property="message", type="string"),
     *                 @OA\Property(property="priority", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function recipientHistory(string $recipientIdentifier, Request $request): JsonResponse
    {
        $limit = $request->query('limit', 15);
        $history = $this->notificationService->getRecipientHistory(
            recipientIdentifier: $recipientIdentifier,
            limit: (int) $limit,
        );

        return response()->json([
            'data' => NotificationRecipientResource::collection($history),
            'current_page' => $history->currentPage(),
            'last_page' => $history->lastPage(),
            'total' => $history->total(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/{id}",
     *     summary="Получить уведомление по ID",
     *     tags={"Notifications"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Информация об уведомлении",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="idempotency_key", type="string"),
     *             @OA\Property(property="channel", type="string"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="batch_id", type="string"),
     *             @OA\Property(property="priority", type="string"),
     *             @OA\Property(property="recipients", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="recipient_identifier", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="attempts", type="integer")
     *             )),
     *             @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Уведомление не найдено")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $notification = Notification::with('recipients')->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return (new NotificationResource($notification))
            ->response()
            ->setStatusCode(200);
    }
}
