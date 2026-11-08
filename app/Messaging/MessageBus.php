<?php

namespace App\Messaging;
interface MessageBus
{
    public function publish(string $subject, array $payload, array $headers = []): void;
}
