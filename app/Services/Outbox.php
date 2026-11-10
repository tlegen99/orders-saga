<?php

namespace App\Services;

use App\Models\OutboxMessage;

final class Outbox
{
    public function record(string $subject, string $type, string $aggregateId, array $payload): OutboxMessage
    {
        return OutboxMessage::create([
            'subject' => $subject,
            'type' => $type,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
        ]);
    }
}
