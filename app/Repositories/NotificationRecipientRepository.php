<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\RecipientStatusData;
use App\Enums\Status;
use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

readonly class NotificationRecipientRepository implements NotificationRecipientRepositoryInterface
{
    public function __construct(
        private NotificationRecipient $model
    ) {}

    public function findByIdWithNotification(string $id): ?NotificationRecipient
    {
        return $this->model::query()
            ->with('notification')->find($id);
    }

    public function findById(string $id): ?NotificationRecipient
    {
        return $this->model::query()->find($id);
    }

    public function bulkInsert(array $recipients, string $notificationId): void
    {
        $recipientData = collect($recipients)->map(fn ($recipient) => [
            'id' => (string) Str::uuid(),
            'notification_id' => $notificationId,
            'recipient_identifier' => $recipient,
            'status' => Status::QUEUED->value,
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        $this->model::query()->insert($recipientData);
    }

    public function findByNotificationId(string $notificationId): Collection
    {
        return $this->model::query()
            ->where('notification_id', $notificationId)
            ->get();
    }

    public function countByNotificationId(string $notificationId): int
    {
        return $this->model::query()
            ->where('notification_id', $notificationId)
            ->count();
    }

    public function markAsSent(string $id): void
    {
        $recipient = $this->model::query()->with('notification')->find($id);
        $recipient?->markAsSent();
    }

    public function markAsDelivered(string $id): void
    {
        $recipient = $this->model::query()->with('notification')->find($id);
        $recipient?->markAsDelivered();
    }

    public function markAsDropped(string $id, string $errorMessage): void
    {
        $recipient = $this->model::query()->with('notification')->find($id);
        $recipient?->markAsDropped($errorMessage);
    }

    public function incrementAttempts(string $id): void
    {
        $this->model::query()->where('id', $id)->increment('attempts');
    }

    public function isFinalized(string $id): bool
    {
        $recipient = $this->model::query()->find($id);

        if (! $recipient) {
            return false;
        }

        return $recipient->isDelivered() || $recipient->isDropped();
    }

    public function findHistoryByRecipient(
        string $recipientIdentifier,
        ?int $limit = null,
        ?int $offset = null
    ): LengthAwarePaginator {
        $query = $this->model::query()
            ->with('notification')
            ->where('recipient_identifier', $recipientIdentifier)
            ->orderBy('created_at', 'desc');

        $perPage = $limit ?? 15;
        $page = $offset !== null ? (int) floor($offset / $perPage) + 1 : null;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getStatusForRecipient(
        string $notificationId,
        string $recipientIdentifier
    ): ?RecipientStatusData {
        $recipient = $this->model::query()
            ->where('notification_id', $notificationId)
            ->where('recipient_identifier', $recipientIdentifier)
            ->with('notification')
            ->first();

        if (! $recipient) {
            return null;
        }

        return new RecipientStatusData(
            notification_id: $recipient->notification_id,
            recipient_identifier: $recipient->recipient_identifier,
            channel: $recipient->notification->channel->value,
            message: $recipient->notification->message,
            priority: $recipient->notification->priority->value,
            status: $recipient->status,
            error_message: $recipient->error_message,
            attempts: $recipient->attempts,
            sent_at: $recipient->sent_at?->toIso8601String(),
            delivered_at: $recipient->delivered_at?->toIso8601String(),
            failed_at: $recipient->failed_at?->toIso8601String(),
            created_at: $recipient->created_at->toIso8601String(),
        );
    }
}
