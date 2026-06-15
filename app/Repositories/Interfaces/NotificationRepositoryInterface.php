<?php

namespace App\Repositories\Interfaces;

use App\DTO\BulkNotificationData;
use App\DTO\RecipientStatusData;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function create(array $data): Notification;

    public function createWithRecipients(BulkNotificationData $data, string $batchId): Notification;

    public function findById(string $id): ?Notification;

    public function findByIdempotencyKey(string $key): ?Notification;

    public function findByBatchId(string $batchId): array;

    public function findRecipientHistory(
        string $recipientIdentifier,
        ?int $limit = null,
        ?int $offset = null
    ): LengthAwarePaginator;

    public function getRecipientStatus(string $notificationId, string $recipientIdentifier): ?RecipientStatusData;
}
