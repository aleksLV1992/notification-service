<?php

namespace App\Repositories;

use App\Enums\Status;
use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

readonly class NotificationRecipientRepository implements NotificationRecipientRepositoryInterface
{
    public function __construct(
        private NotificationRecipient $model
    )
    {
    }

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
        $recipientData = collect($recipients)->map(fn($recipient) => [
            'id' => (string) Str::uuid(),
            'notification_id' => $notificationId,
            'recipient_identifier' => $recipient,
            'status' => Status::QUEUED,
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
        $this->model::query()
            ->where('id', $id)
            ->update([
                'status' => Status::SENT,
                'sent_at' => now(),
            ]);
    }

    public function markAsDelivered(string $id): void
    {
        $this->model::query()
            ->where('id', $id)
            ->update([
                'status' => Status::DELIVERED,
                'delivered_at' => now(),
            ]);
    }

    public function markAsFailed(string $id, string $errorMessage): void
    {
        $this->model::query()
            ->where('id', $id)
            ->update([
                'status' => Status::FAILED,
                'error_message' => $errorMessage,
                'failed_at' => now(),
            ]);
    }
}
