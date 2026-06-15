<?php

namespace App\Jobs;

use App\Actions\Notification\SendNotificationAction;
use App\Models\NotificationRecipient;
use App\Services\Interfaces\MetricsInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public string $recipientId,
        public string $channel,
        public string $message,
        public Priority $priority,
        public string $notificationId,
    ) {}

    public function handle(
        SendNotificationAction $sendAction,
        MetricsInterface $metrics
    ): void {
        $recipient = $this->getRecipient();

        if (!$recipient) {
            $this->handleRecipientNotFound($metrics);
            return;
        }

        $success = $sendAction->execute(
            recipient: $recipient,
            message: $this->message,
            notificationId: $this->notificationId,
        );

        if (!$success) {
            $metrics->incrementSendError(
                channel: $this->channel,
                errorType: 'provider_failed',
            );
            throw new \Exception('Failed to send notification');
        }
    }

    public function failed(\Throwable $exception): void
    {
        $recipient = $this->getRecipient();

        if ($recipient) {
            $sendAction = app(SendNotificationAction::class);
            $sendAction->handleFailure(
                recipient: $recipient,
                errorMessage: $exception->getMessage(),
            );
        }

        $metrics = app(MetricsInterface::class);
        $metrics->incrementSendError(
            channel: $this->channel,
            errorType: 'job_failed',
        );
    }

    private function getRecipient(): ?NotificationRecipient
    {
        $recipientRepo = app(\App\Repositories\Interfaces\NotificationRecipientRepositoryInterface::class);
        return $recipientRepo->findByIdWithNotification($this->recipientId);
    }

    private function handleRecipientNotFound(MetricsInterface $metrics): void
    {
        \Log::warning('Recipient not found', ['id' => $this->recipientId]);
        $metrics->incrementSendError(
            channel: $this->channel,
            errorType: 'recipient_not_found',
        );
    }
}
