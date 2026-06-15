<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function getQueueName(): string
    {
        return $this->priority->getQueueName();
    }
}
