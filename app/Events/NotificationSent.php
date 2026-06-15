<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\NotificationRecipient;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public NotificationRecipient $recipient,
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
}
