<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $idempotency_key
 * @property Channel $channel
 * @property string $message
 * @property string|null $batch_id
 * @property Priority $priority
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Collection<int, NotificationRecipient> $recipients
 */
class Notification extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'idempotency_key',
        'channel',
        'message',
        'batch_id',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'priority' => Priority::class,
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Notification $notification) {
            if (empty($notification->id)) {
                $notification->id = (string) Str::uuid();
            }
            if (empty($notification->idempotency_key)) {
                $notification->idempotency_key = Str::uuid();
            }
        });
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function isCritical(): bool
    {
        return $this->priority === Priority::CRITICAL;
    }

    public function isMarketing(): bool
    {
        return $this->priority === Priority::MARKETING;
    }

    public function getQueueName(): string
    {
        return $this->priority->getQueueName();
    }
}
