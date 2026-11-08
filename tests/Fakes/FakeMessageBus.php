<?php

namespace Tests\Fakes;

use App\Messaging\MessageBus;
use RuntimeException;

/**
 * шина для тестов: ничего никуда не отправляет, а складывает сообщения в массив.
 * Если $shouldFail = true - имитирует недоступный брокер.
 */
class FakeMessageBus implements MessageBus
{
    /** @var list<array{subject: string, payload: array, headers: array}> */
    public array $published = [];

    public bool $shouldFail = false;

    public function publish(string $subject, array $payload, array $headers = []): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Broker is unavailable');
        }

        $this->published[] = compact('subject', 'payload', 'headers');
    }
}
