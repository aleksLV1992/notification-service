<?php

namespace App\Http\Resources;

use App\Http\Resources\NotificationRecipientResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
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
