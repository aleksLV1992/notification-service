<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\Status;
use Spatie\LaravelData\Data;

class RecipientStatusData extends Data
{
    public function __construct(
        public string $notification_id,
        public string $recipient_identifier,
        public string $channel,
        public string $message,
        public string $priority,
        public Status $status,
        public ?string $error_message,
        public int $attempts,
        public ?string $sent_at,
        public ?string $delivered_at,
        public ?string $failed_at,
        public string $created_at,
    ) {}
}
