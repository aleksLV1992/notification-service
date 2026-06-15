<?php

namespace App\Services\Providers;

interface NotificationProviderInterface
{
    public function send(string $recipient, string $message): bool;

    public function getProviderName(): string;
}
