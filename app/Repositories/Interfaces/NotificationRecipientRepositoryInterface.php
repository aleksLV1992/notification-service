<?php

namespace App\Repositories\Interfaces;

use App\Models\NotificationRecipient;
use Illuminate\Database\Eloquent\Collection;

interface NotificationRecipientRepositoryInterface
{
    /**
     * Найти получателя по ID с уведомлением.
     */
    public function findByIdWithNotification(string $id): ?NotificationRecipient;

    /**
     * Найти получателя по ID.
     */
    public function findById(string $id): ?NotificationRecipient;

    /**
     * Массовая вставка получателей.
     */
    public function bulkInsert(array $recipients, string $notificationId): void;

    /**
     * Получить всех получателей уведомления.
     */
    public function findByNotificationId(string $notificationId): Collection;

    /**
     * Получить количество получателей уведомления.
     */
    public function countByNotificationId(string $notificationId): int;

    /**
     * Отметить как отправлено.
     */
    public function markAsSent(string $id): void;

    /**
     * Отметить как доставлено.
     */
    public function markAsDelivered(string $id): void;

    /**
     * Отметить как failed.
     */
    public function markAsFailed(string $id, string $errorMessage): void;
}
