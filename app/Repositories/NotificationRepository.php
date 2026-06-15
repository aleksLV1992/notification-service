<?php

namespace App\Repositories;

use App\DTO\BulkNotificationData;
use App\DTO\RecipientStatusData;
use App\Models\Notification as EloquentNotification;
use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private readonly EloquentNotification                     $model,
        private readonly NotificationRecipientRepositoryInterface $recipientRepository,
    ) {}

    public function create(array $data): EloquentNotification
    {
        return $this->model::create($data);
    }

    /**
     * @throws \Throwable
     */
    public function createWithRecipients(BulkNotificationData $data, string $batchId): EloquentNotification
    {
        return DB::transaction(function () use ($data, $batchId) {
            $notification = $this->model::create([
                'idempotency_key' => $data->idempotency_key,
                'channel' => $data->channel,
                'message' => $data->message,
                'batch_id' => $batchId,
                'priority' => $data->priority,
                'metadata' => $data->metadata,
            ]);

            $this->recipientRepository->bulkInsert(
                recipients: $data->recipients,
                notificationId: $notification->id
            );

            return $this->model::query()
                ->with('recipients')
                ->find($notification->id);
        });
    }

    public function findById(string $id): ?EloquentNotification
    {
        return $this->model::query()
            ->with('recipients')
            ->find($id);
    }

    public function findByIdempotencyKey(string $key): ?EloquentNotification
    {
        return $this->model::query()
            ->where('idempotency_key', $key)
            ->first();
    }

    public function findByBatchId(string $batchId): array
    {
        return $this->model::query()
            ->where('batch_id', $batchId)
            ->with('recipients')
            ->get()
            ->toArray();
    }

    public function findRecipientHistory(
        string $recipientIdentifier,
        ?int $limit = null,
        ?int $offset = null
    ): LengthAwarePaginator {
        $query = $this->model::query()
            ->with('notification')
            ->where('recipient_identifier', $recipientIdentifier)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->paginate(15);
    }

    public function getRecipientStatus(string $notificationId, string $recipientIdentifier): ?RecipientStatusData
    {
        $recipient = $this->model::query()
            ->where('notification_id', $notificationId)
            ->where('recipient_identifier', $recipientIdentifier)
            ->with('notification')
            ->first();

        if (!$recipient) {
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
