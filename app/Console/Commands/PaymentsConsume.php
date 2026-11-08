<?php

namespace App\Console\Commands;

use App\Payments\ChargePaymentHandler;
use Basis\Nats\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:consume')]
#[Description('Фоновый воркер для обработки платежных сообщений из NATS JetStream')]
class PaymentsConsume extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Client $client, ChargePaymentHandler $handler): int
    {
        $queue = $client->getApi()->getStream('SAGA')->getConsumer('payments')->getQueue();

        $stop = false;
        $this->trap([SIGTERM, SIGINT], function () use (&$stop) {
            $stop = true;
        });

        $this->info('Жду сообщения из payments.charge... (Ctrl+C - выход)');

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

            try {
                $handler->handle(
                    $messageId,
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
