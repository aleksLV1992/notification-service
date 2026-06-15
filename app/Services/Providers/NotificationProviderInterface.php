<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface NotificationProviderInterface
{
    public function send(string $recipient, string $message): bool;

    public function confirmDelivery(string $recipient): bool;

    public function getProviderName(): string;
}
