<?php

namespace App\DTO;

use Spatie\LaravelData\Attributes\Validation\{In, Max, Min, Uuid, Required};
use Spatie\LaravelData\Data;

class BulkNotificationData extends Data
{
    public function __construct(
        #[Required]
        #[In(['sms', 'email'])]
        public string $channel,

        #[Required]
        #[Max(1000)]
        public string $message,

        #[Required]
        #[Min(1)]
        public array $recipients,

        #[In(['critical', 'normal', 'marketing'])]
        public string $priority = 'normal',

        #[Uuid]
        public ?string $idempotency_key = null,

        public ?array $metadata = null,
    ) {}
}
