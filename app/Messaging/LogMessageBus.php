<?php

namespace App\Messaging;

use Illuminate\Support\Facades\Log;

class LogMessageBus implements MessageBus
{
    public function publish(string $subject, array $payload, array $headers = []): void
    {
        // логируем инфу временно
        Log::info("MessageBus [{$subject}] published", [
            'payload' => $payload,
            'headers' => $headers,
        ]);
    }
}
