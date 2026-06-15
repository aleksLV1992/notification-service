<?php

declare(strict_types=1);

namespace App\Enums;

enum Status: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case DROPPED = 'dropped';

    public function isQueued(): bool
    {
        return $this === self::QUEUED;
    }

    public function isSent(): bool
    {
        return $this === self::SENT;
    }

    public function isDelivered(): bool
    {
        return $this === self::DELIVERED;
    }

    public function isDropped(): bool
    {
        return $this === self::DROPPED;
    }
}
