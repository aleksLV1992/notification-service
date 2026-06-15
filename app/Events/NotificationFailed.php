<?php

namespace App\Events;

use App\Models\NotificationRecipient;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public NotificationRecipient $recipient,
        public string $errorMessage,
    ) {}

    public function getRecipientIdentifier(): string
    {
        return $this->recipient->recipient_identifier;
    }

    public function getChannel(): string
    {
        return $this->recipient->notification->channel->value;
    }

    public function getPriority(): string
    {
        return $this->recipient->notification->priority->value;
    }

    public function getAttempts(): int
    {
        return $this->recipient->attempts;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
