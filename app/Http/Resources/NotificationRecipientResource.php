<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\NotificationRecipient
 */
class NotificationRecipientResource extends JsonResource
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
            'notification_id' => $this->notification_id,
            'recipient_identifier' => $this->recipient_identifier,
            'channel' => $this->when($this->relationLoaded('notification'), fn () => $this->notification->channel->value),
            'message' => $this->when($this->relationLoaded('notification'), fn () => $this->notification->message),
            'priority' => $this->when($this->relationLoaded('notification'), fn () => $this->notification->priority->value),
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'attempts' => $this->attempts,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
