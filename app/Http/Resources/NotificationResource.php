<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'channel' => $this->channel->value,
            'message' => $this->message,
            'batch_id' => $this->batch_id,
            'priority' => $this->priority->value,
            'metadata' => $this->metadata,
            'recipients_count' => $this->whenCounted('recipients'),
            'recipients' => NotificationRecipientResource::collection($this->whenLoaded('recipients')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
