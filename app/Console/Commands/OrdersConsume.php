<?php

namespace App\Console\Commands;

use App\Orders\OrderSagaHandler;
use Basis\Nats\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:consume')]
#[Description('Фоновый воркер для обработки ответов саги из NATS JetStream')]
class OrdersConsume extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Client $client, OrderSagaHandler $handler): int
    {
        $queue = $client->getApi()->getStream('SAGA')->getConsumer('orders')->getQueue();

        $stop = false;
        $this->trap([SIGTERM, SIGINT], function () use (&$stop) {
            $stop = true;
        });

        $this->info('Жду сообщения из orders.*... (Ctrl+C - выход)');

        while (! $stop) {
            $message = $queue->fetch();
            if ($message === null) {
                continue;
            }

            // служебный ответ JetStream (например, 404 "нет сообщений") - не наше сообщение, ack не нужен
            if ($message->payload->isEmpty()) {
                continue;
            }

            $messageId = $message->payload->getHeader('message_id');
            if ($messageId === null) {
                $this->error('Message without message_id, skipping');
                $message->ack();

                continue;
            }

            $type = $message->payload->getHeader('type');
            if ($type === null) {
                $this->error('Message without type, skipping');
                $message->ack();

                continue;
            }

            try {
                $handler->handle(
                    $messageId,
                    $type,
                    json_decode($message->payload->body, true, flags: JSON_THROW_ON_ERROR),
                );
                $message->ack();
                $this->line("Обработано: {$messageId}");
            } catch (\Throwable $e) {
                report($e);
                $this->error("Ошибка: {$e->getMessage()}");
                $message->nack(5);
            }
        }

        return self::SUCCESS;
    }
}
