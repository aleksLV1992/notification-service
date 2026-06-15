<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkNotificationData;
use App\DTO\NotificationResult;
use App\DTO\RecipientStatusData;
use App\Enums\Priority;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Interfaces\MetricsInterface;
use App\Services\RateLimiter\DeduplicationService;
use App\Services\RateLimiter\RateLimiterService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private NotificationRecipientRepositoryInterface $recipientRepository,
        private DeduplicationService $deduplicationService,
        private RateLimiterService $rateLimiter,
        private MetricsInterface $metrics,
    ) {}

    public function sendBulkNotification(BulkNotificationData $data): NotificationResult
    {
        $idempotencyKey = $data->idempotency_key ?? Str::uuid()->toString();
        $reservedKey = null;

        if ($data->idempotency_key !== null) {
            $duplicateResult = $this->checkForDuplicate(
                idempotencyKey: $data->idempotency_key,
                channel: $data->channel,
            );
            if ($duplicateResult !== null) {
                return $duplicateResult;
            }

            if (! $this->deduplicationService->tryReserve($data->idempotency_key)) {
                return $this->resolveInFlightDuplicate(
                    idempotencyKey: $data->idempotency_key,
                    channel: $data->channel,
                );
            }

            $reservedKey = $data->idempotency_key;
        }

        if ($rateLimitResult = $this->validateRateLimits($data)) {
            $this->releaseReservation($reservedKey);

            return $rateLimitResult;
        }

        $batchId = Str::uuid()->toString();
        if ($data->idempotency_key === null) {
            $data = new BulkNotificationData(
                channel: $data->channel,
                message: $data->message,
                recipients: $data->recipients,
                priority: $data->priority,
                idempotency_key: $idempotencyKey,
                metadata: $data->metadata,
            );
        }

        try {
            $notification = $this->notificationRepository->createWithRecipients(
                data: $data,
                batchId: $batchId,
            );
        } catch (QueryException $e) {
            $this->releaseReservation($reservedKey);

            if ($this->isUniqueIdempotencyViolation($e) && $data->idempotency_key !== null) {
                $existing = $this->notificationRepository->findByIdempotencyKey($data->idempotency_key);
                if ($existing) {
                    $this->metrics->incrementDuplicateDetected($data->channel);

                    return NotificationResult::duplicate(
                        notification: $existing,
                        message: 'Duplicate request',
                    );
                }
            }

            throw $e;
        }

        $this->dispatchNotificationJobs($notification);
        $this->recordRateLimits($data);

        if ($reservedKey !== null) {
            $this->deduplicationService->markAsProcessed(
                idempotencyKey: $reservedKey,
                notificationId: $notification->id,
            );
        }

        return NotificationResult::success($notification);
    }

    private function checkForDuplicate(string $idempotencyKey, string $channel): ?NotificationResult
    {
        $existing = $this->notificationRepository->findByIdempotencyKey($idempotencyKey);
        if ($existing) {
            $this->metrics->incrementDuplicateDetected($channel);

            return NotificationResult::duplicate(
                notification: $existing,
                message: 'Duplicate request',
            );
        }

        if (! $this->deduplicationService->isDuplicate($idempotencyKey)) {
            return null;
        }

        return $this->resolveInFlightDuplicate($idempotencyKey, $channel);
    }

    private function resolveInFlightDuplicate(string $idempotencyKey, string $channel): NotificationResult
    {
        $existingNotificationId = $this->deduplicationService->getNotificationId($idempotencyKey);
        if ($existingNotificationId) {
            $existing = $this->notificationRepository->findById($existingNotificationId);
            if ($existing) {
                $this->metrics->incrementDuplicateDetected($channel);

                return NotificationResult::duplicate(
                    notification: $existing,
                    message: 'Duplicate request',
                );
            }
        }

        $this->metrics->incrementDuplicateDetected($channel);

        return NotificationResult::processing('Duplicate request is still processing');
    }

    private function validateRateLimits(BulkNotificationData $data): ?NotificationResult
    {
        if ($this->isMarketingPriority($data->priority)) {
            return $this->validateMarketingRateLimit($data);
        }

        if ($data->priority === Priority::CRITICAL->value) {
            return $this->validateCriticalRateLimit($data);
        }

        return null;
    }

    private function validateMarketingRateLimit(BulkNotificationData $data): ?NotificationResult
    {
        foreach ($data->recipients as $recipient) {
            if ($this->rateLimiter->exceedsMarketingLimit($recipient)) {
                $this->metrics->incrementRateLimitExceeded(
                    priority: $data->priority,
                    recipient: $recipient,
                );

                return NotificationResult::rateLimitExceeded(
                    message: 'Rate limit exceeded for marketing notifications',
                );
            }
        }

        return null;
    }

    private function validateCriticalRateLimit(BulkNotificationData $data): ?NotificationResult
    {
        foreach ($data->recipients as $recipient) {
            if (! $this->rateLimiter->canSendCritical($recipient)) {
                $this->metrics->incrementRateLimitExceeded(
                    priority: $data->priority,
                    recipient: $recipient,
                );

                return NotificationResult::rateLimitExceeded(
                    message: 'Rate limit exceeded for critical notifications',
                );
            }
        }

        return null;
    }

    private function recordRateLimits(BulkNotificationData $data): void
    {
        if ($this->isMarketingPriority($data->priority)) {
            foreach ($data->recipients as $recipient) {
                $this->rateLimiter->recordMarketingSend($recipient);
            }

            return;
        }

        if ($data->priority === Priority::CRITICAL->value) {
            foreach ($data->recipients as $recipient) {
                $this->rateLimiter->recordCriticalSend($recipient);
            }
        }
    }

    private function isMarketingPriority(string $priority): bool
    {
        return $priority === Priority::MARKETING->value;
    }

    private function dispatchNotificationJobs(Notification $notification): void
    {
        $queueName = $notification->getQueueName();

        foreach ($notification->recipients as $recipient) {
            SendNotificationJob::dispatch(
                recipientId: $recipient->id,
                channel: $notification->channel->value,
                message: $notification->message,
                priority: $notification->priority,
                notificationId: $notification->id,
            )->onQueue($queueName);
        }
    }

    private function releaseReservation(?string $reservedKey): void
    {
        if ($reservedKey !== null) {
            $this->deduplicationService->release($reservedKey);
        }
    }

    private function isUniqueIdempotencyViolation(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'idempotency_key')
            || $e->getCode() === '23505';
    }

    public function getNotificationById(string $id): ?Notification
    {
        return $this->notificationRepository->findById($id);
    }

    public function getRecipientHistory(string $recipientIdentifier, int $limit = 15, int $offset = 0)
    {
        return $this->recipientRepository->findHistoryByRecipient(
            recipientIdentifier: $recipientIdentifier,
            limit: $limit,
            offset: $offset,
        );
    }

    public function getNotificationStatus(string $notificationId, string $recipientIdentifier): ?RecipientStatusData
    {
        return $this->recipientRepository->getStatusForRecipient(
            notificationId: $notificationId,
            recipientIdentifier: $recipientIdentifier,
        );
    }
}
