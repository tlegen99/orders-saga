<?php

namespace App\Messaging;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use RuntimeException;

class NatsMessageBus implements MessageBus
{
    public function __construct(private Client $client) {}

    public function publish(string $subject, array $payload, array $headers = []): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $natsHeaders = array_map(function ($value) {
            return (string) $value;
        }, $headers);

        // добавляем обязательный заголовок для дедупликации в JetStream NATS
        if (isset($headers['message_id'])) {
            $natsHeaders['Nats-Msg-Id'] = (string) $headers['message_id'];
        }

        $natsPayload = new Payload($body, $natsHeaders);

        $ack = null;
        // request ждёт PubAck: JetStream отвечает только после того, как сохранил сообщение
        $this->client->request($subject, $natsPayload, function (Payload $response) use (&$ack) {
            $ack = $response;
        });

        if ($ack === null) {
            throw new RuntimeException("JetStream did not confirm message on [{$subject}]");
        }

        $data = json_decode($ack->body, true, flags: JSON_THROW_ON_ERROR);

        if (isset($data['error'])) {
            throw new RuntimeException('JetStream error: '.($data['error']['description'] ?? 'unknown'));
        }
    }
}
