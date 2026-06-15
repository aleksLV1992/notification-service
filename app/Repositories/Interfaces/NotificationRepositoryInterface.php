<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\DTO\BulkNotificationData;
use App\Models\Notification;

interface NotificationRepositoryInterface
{
    public function create(array $data): Notification;

    public function createWithRecipients(BulkNotificationData $data, string $batchId): Notification;

    public function findById(string $id): ?Notification;

    public function findByIdempotencyKey(string $key): ?Notification;

    public function findByBatchId(string $batchId): array;
}
