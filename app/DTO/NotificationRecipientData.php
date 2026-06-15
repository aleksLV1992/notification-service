<?php

namespace App\DTO;

use App\Enums\Status;
use Spatie\LaravelData\Data;

class NotificationRecipientData extends Data
{
    public function __construct(
        public string $recipient_identifier,
        public Status $status = Status::QUEUED,
        public int $attempts = 0,
    ) {}
}
