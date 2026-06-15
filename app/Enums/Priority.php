<?php

namespace App\Enums;

enum Priority: string
{
    case CRITICAL = 'critical';
    case NORMAL = 'normal';
    case MARKETING = 'marketing';

    public function getQueueName(): string
    {
        return match ($this) {
            self::CRITICAL => 'critical',
            self::MARKETING => 'marketing',
            self::NORMAL => 'default',
        };
    }

    public function isCritical(): bool
    {
        return $this === self::CRITICAL;
    }

    public function isMarketing(): bool
    {
        return $this === self::MARKETING;
    }
}
