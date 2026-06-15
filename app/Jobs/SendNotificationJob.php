<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notification\SendNotificationAction;
use App\Enums\Priority;
use App\Exceptions\CircuitBreakerOpenException;
use App\Repositories\Interfaces\NotificationRecipientRepositoryInterface;
use App\Services\Interfaces\MetricsInterface;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        MetricsInterface $metrics,
        NotificationRecipientRepositoryInterface $recipientRepo,
    ): void {
        if ($recipientRepo->isFinalized($this->recipientId)) {
            return;
        }

        $recipient = $recipientRepo->findByIdWithNotification($this->recipientId);

        if ($recipient === null) {
            $this->handleRecipientNotFound($metrics);

            return;
        }

        $recipientRepo->incrementAttempts($this->recipientId);
        $recipient->refresh();

        try {
            $success = $sendAction->execute(
                recipient: $recipient,
                message: $this->message,
                notificationId: $this->notificationId,
            );

            if (! $success) {
                $metrics->incrementSendError(
                    channel: $this->channel,
                    errorType: 'provider_failed',
                );

                if ($this->attempts() >= $this->tries) {
                    $sendAction->handleFailure(
                        recipient: $recipient,
                        errorMessage: 'Failed to send notification',
                    );

                    return;
                }

                throw new Exception('Failed to send notification');
            }
        } catch (CircuitBreakerOpenException $e) {
            $metrics->incrementSendError(
                channel: $this->channel,
                errorType: 'circuit_breaker_open',
            );
            $sendAction->handleFailure(
                recipient: $recipient,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($exception instanceof CircuitBreakerOpenException) {
            return;
        }

        $recipientRepo = app(NotificationRecipientRepositoryInterface::class);
        $sendAction = app(SendNotificationAction::class);
        $metrics = app(MetricsInterface::class);

        $recipient = $recipientRepo->findByIdWithNotification($this->recipientId);

        if ($recipient !== null && ! $recipient->isDropped()) {
            $sendAction->handleFailure(
                recipient: $recipient,
                errorMessage: $exception->getMessage(),
            );
        }

        $metrics->incrementSendError(
            channel: $this->channel,
            errorType: 'job_failed',
        );
    }

    private function handleRecipientNotFound(MetricsInterface $metrics): void
    {
        Log::warning('Recipient not found', ['id' => $this->recipientId]);
        $metrics->incrementSendError(
            channel: $this->channel,
            errorType: 'recipient_not_found',
        );
    }
}
