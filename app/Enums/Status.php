<?php

namespace App\Enums;

enum Status: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

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

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }
}
