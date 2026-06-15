<?php

namespace App\Services;

use App\DTO\BulkNotificationData;
use App\DTO\NotificationResult;
use App\DTO\RecipientStatusData;
use App\Enums\Priority;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Factories\NotificationProviderFactory;
use App\Services\RateLimiter\DeduplicationService;
use App\Services\RateLimiter\RateLimiterService;
use App\Services\Metrics\MetricsService;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private NotificationRecipientRepositoryInterface $recipientRepository,
        private DeduplicationService $deduplicationService,
        private NotificationProviderFactory $providerFactory,
        private RateLimiterService $rateLimiter,
        private MetricsService $metrics,
    ) {}

    /**
     * Отправка массового уведомления.
     * Возвращает DTO с результатом операции.
     */
    public function sendBulkNotification(BulkNotificationData $data): NotificationResult
    {
        $idempotencyKey = $data->idempotency_key ?? Str::uuid()->toString();

        // Проверка на дубликат
        $duplicateResult = $this->checkForDuplicate(
            idempotencyKey: $idempotencyKey,
            channel: $data->channel
        );
        if ($duplicateResult !== null) {
            return $duplicateResult;
        }

        // Проверка rate limit для marketing
        if ($data->priority === Priority::MARKETING->value) {
            foreach ($data->recipients as $recipient) {
                if (!$this->rateLimiter->canSendMarketing($recipient)) {
                    $this->metrics->incrementRateLimitExceeded(
                        priority: $data->priority,
                        recipient: $recipient
                    );

                    return NotificationResult::rateLimitExceeded(
                        message: 'Rate limit exceeded for marketing notifications'
                    );
                }
            }
        }

        // Создание уведомления с получателями (транзакция внутри репозитория)
        $batchId = Str::uuid()->toString();
        $notification = $this->notificationRepository->createWithRecipients(
            data: $data,
            batchId: $batchId
        );

        // Диспатч джоб после успешной транзакции
        $this->dispatchNotificationJobs($notification);

        // Помечаем как обработанный для дедубликации
        $this->deduplicationService->markAsProcessed(
            idempotencyKey: $idempotencyKey,
            notificationId: $notification->id
        );

        // Метрики
        $this->metrics->incrementNotificationSent(
            channel: $data->channel,
            priority: $data->priority
        );

        return NotificationResult::success($notification);
    }

    /**
     * Проверка на дубликат по idempotency key.
     * Возвращает результат дубликата или null если дубликата нет.
     */
    private function checkForDuplicate(string $idempotencyKey, string $channel): ?NotificationResult
    {
        if (!$this->deduplicationService->isDuplicate($idempotencyKey)) {
            return null;
        }

        $existingNotificationId = $this->deduplicationService->getNotificationId($idempotencyKey);
        if (!$existingNotificationId) {
            return null;
        }

        $existing = $this->notificationRepository->findById($existingNotificationId);
        if (!$existing) {
            return null;
        }

        $this->metrics->incrementDuplicateDetected($channel);

        return NotificationResult::duplicate(
            notification: $existing,
            message: 'Duplicate request'
        );
    }

    /**
     * Диспатч джоб для всех получателей уведомления.
     */
    private function dispatchNotificationJobs(Notification $notification): void
    {
        $queueName = $notification->getQueueName();

        foreach ($notification->recipients as $recipient) {
            SendNotificationJob::dispatch(
                recipientId: $recipient->id,
                channel: $notification->channel->value,
                message: $notification->message,
                priority: $notification->priority,
                notificationId: $notification->id
            )->onQueue($queueName);
        }
    }

    public function getRecipientHistory(string $recipientIdentifier, int $limit = 15, int $offset = 0)
    {
        return $this->notificationRepository->findRecipientHistory(
            recipientIdentifier: $recipientIdentifier,
            limit: $limit,
            offset: null,
        );
    }

    public function getNotificationStatus(string $notificationId, string $recipientIdentifier): ?RecipientStatusData
    {
        return $this->notificationRepository->getRecipientStatus(
            notificationId: $notificationId,
            recipientIdentifier: $recipientIdentifier,
        );
    }
}
