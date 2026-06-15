<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\BulkNotificationData;
use App\Models\Notification as EloquentNotification;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Support\Facades\DB;

readonly class NotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private readonly EloquentNotification $model,
        private readonly NotificationRecipientRepositoryInterface $recipientRepository,
    ) {}

    public function create(array $data): EloquentNotification
    {
        return $this->model::create($data);
    }

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
}
