<?php

namespace App\Actions\Notification;

use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Services\Providers\NotificationProviderInterface;
use Illuminate\Support\Facades\Log;

readonly class SendNotificationAction
{
    public function __construct(
        private NotificationRecipientRepositoryInterface $recipientRepo,
        private NotificationProviderInterface            $provider,
    ) {}

    public function execute(
        NotificationRecipient $recipient,
        string $message,
        string $notificationId
    ): bool {
        try {
            $this->recipientRepo->markAsSent(id: $recipient->id);

            $success = $this->provider->send(
                recipient: $recipient->recipient_identifier,
                message: $message,
            );

            if ($success) {
                $this->recipientRepo->markAsDelivered(
                    id: $recipient->id,
                );

                return true;
            }

            Log::warning('Notification provider failed', [
                'recipient_id' => $recipient->id,
                'notification_id' => $notificationId,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Notification send failed', [
                'recipient_id' => $recipient->id,
                'notification_id' => $notificationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function handleFailure(NotificationRecipient $recipient, string $errorMessage): void
    {
        $this->recipientRepo->markAsFailed(
            id: $recipient->id,
            errorMessage: $errorMessage,
        );

        Log::error('Notification delivery failed', [
            'recipient_id' => $recipient->id,
            'error' => $errorMessage,
        ]);
    }
}
