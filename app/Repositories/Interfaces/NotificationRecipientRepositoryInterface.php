<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\DTO\RecipientStatusData;
use App\Models\NotificationRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface NotificationRecipientRepositoryInterface
{
    public function findByIdWithNotification(string $id): ?NotificationRecipient;

    public function findById(string $id): ?NotificationRecipient;

    public function bulkInsert(array $recipients, string $notificationId): void;

    public function findByNotificationId(string $notificationId): Collection;

    public function countByNotificationId(string $notificationId): int;

    public function markAsSent(string $id): void;

    public function markAsDelivered(string $id): void;

    public function markAsDropped(string $id, string $errorMessage): void;

    public function incrementAttempts(string $id): void;

    public function isFinalized(string $id): bool;

    public function findHistoryByRecipient(
        string $recipientIdentifier,
        ?int $limit = null,
        ?int $offset = null
    ): LengthAwarePaginator;

    public function getStatusForRecipient(
        string $notificationId,
        string $recipientIdentifier
    ): ?RecipientStatusData;
}
