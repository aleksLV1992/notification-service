<?php

namespace App\Models;

use App\Enums\Status;
use App\Events\NotificationDelivered;
use App\Events\NotificationFailed;
use App\Events\NotificationSent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $notification_id
 * @property string $recipient_identifier
 * @property Status $status
 * @property string|null $error_message
 * @property int $attempts
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $delivered_at
 * @property \Carbon\Carbon|null $failed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Notification $notification
 */
class NotificationRecipient extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'notification_id',
        'recipient_identifier',
        'status',
        'error_message',
        'attempts',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'status' => Status::class,
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $dispatchesEvents = [];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (NotificationRecipient $recipient) {
            if (empty($recipient->id)) {
                $recipient->id = (string) Str::uuid();
            }
        });
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function isQueued(): bool
    {
        return $this->status === Status::QUEUED;
    }

    public function isSent(): bool
    {
        return $this->status === Status::SENT;
    }

    public function isDelivered(): bool
    {
        return $this->status === Status::DELIVERED;
    }

    public function isFailed(): bool
    {
        return $this->status === Status::FAILED;
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => Status::SENT,
            'sent_at' => now(),
        ]);

        event(new NotificationSent($this));
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => Status::DELIVERED,
            'delivered_at' => now(),
        ]);

        event(new NotificationDelivered($this));
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => Status::FAILED,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);

        event(new NotificationFailed($this, $errorMessage));
    }
}
