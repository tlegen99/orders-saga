<?php

namespace Tests\Fakes;

use App\Messaging\MessageBus;
use RuntimeException;

/**
 * шина для тестов: ничего никуда не отправляет, а складывает сообщения в массив.
 * Если $shouldFail = true - имитирует недоступный брокер,
 * $failSubjects - «сломанные» subjects, на которых падает только конкретное письмо.
 */
class FakeMessageBus implements MessageBus
{
    /** @var list<array{subject: string, payload: array, headers: array}> */
    public array $published = [];

    public bool $shouldFail = false;

    /** @var list<string> */
    public array $failSubjects = [];

    public function publish(string $subject, array $payload, array $headers = []): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Broker is unavailable');
        }

        if (in_array($subject, $this->failSubjects, true)) {
            throw new RuntimeException("Cannot publish to [{$subject}]");
        }

        $this->published[] = compact('subject', 'payload', 'headers');
    }
}
