<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Exceptions\CircuitBreakerOpenException;
use App\Models\NotificationRecipient;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Services\Factories\NotificationProviderFactory;
use Illuminate\Support\Facades\Log;

readonly class SendNotificationAction
{
    public function __construct(
        private NotificationRecipientRepositoryInterface $recipientRepo,
        private NotificationProviderFactory $providerFactory,
    ) {}

    public function execute(
        NotificationRecipient $recipient,
        string $message,
        string $notificationId
    ): bool {
        $recipient->refresh();

        if ($recipient->isDelivered()) {
            return true;
        }

        if ($recipient->isDropped()) {
            return false;
        }

        if ($recipient->isSent()) {
            return $this->confirmDelivery($recipient);
        }

        try {
            $channel = $recipient->notification->channel->value;
            $provider = $this->providerFactory->get($channel);

            $success = $provider->send(
                recipient: $recipient->recipient_identifier,
                message: $message,
            );

            if (! $success) {
                Log::warning('Notification provider failed', [
                    'recipient_id' => $recipient->id,
                    'notification_id' => $notificationId,
                ]);

                return false;
            }

            $this->recipientRepo->markAsSent(id: $recipient->id);

            return $this->confirmDelivery($recipient->fresh(['notification']));

        } catch (CircuitBreakerOpenException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Notification send failed', [
                'recipient_id' => $recipient->id,
                'notification_id' => $notificationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function confirmDelivery(NotificationRecipient $recipient): bool
    {
        $channel = $recipient->notification->channel->value;
        $provider = $this->providerFactory->get($channel);

        if (! $provider->confirmDelivery($recipient->recipient_identifier)) {
            Log::warning('Notification delivery confirmation failed', [
                'recipient_id' => $recipient->id,
            ]);

            return false;
        }

        $this->recipientRepo->markAsDelivered(id: $recipient->id);

        return true;
    }

    public function handleFailure(NotificationRecipient $recipient, string $errorMessage): void
    {
        $this->recipientRepo->markAsDropped(
            id: $recipient->id,
            errorMessage: $errorMessage,
        );

        Log::error('Notification delivery failed', [
            'recipient_id' => $recipient->id,
            'error' => $errorMessage,
        ]);
    }
}
