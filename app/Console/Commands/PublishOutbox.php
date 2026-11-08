<?php

namespace App\Console\Commands;

use App\Messaging\MessageBus;
use App\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishOutbox extends Command
{
    protected $signature = 'outbox:publish {--once}';

    protected $description = 'Публикует сообщения из outbox в брокер';

    public function handle(MessageBus $bus): int
    {
        $stop = false;
        $this->trap([SIGTERM, SIGINT], function () use (&$stop) {
            $stop = true;
        });

        do {
            $sent = $this->publishBatch($bus);

            if ($sent === 0 && ! $this->option('once')) {
                usleep(500_000);
            }
        } while (! $this->option('once') && ! $stop);

        return self::SUCCESS;
    }

    private function publishBatch(MessageBus $bus): int
    {
        return DB::transaction(function () use ($bus) {
            $messages = OutboxMessage::whereNull('published_at')
                ->orderBy('created_at')
                ->limit(50)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            $sent = 0;
            foreach ($messages as $message) {
                try {
                    $bus->publish(
                        $message->subject,
                        $message->payload,
                        ($message->headers ?? []) + ['message_id' => $message->id, 'type' => $message->type],
                    );
                    $message->published_at = now();
                    $sent++;
                } catch (\Throwable $e) {
                    $message->attempts++;
                    $message->last_error = $e->getMessage();
                    $message->save();
                    report($e);
                    break;
                }
                $message->save();
            }

            return $sent;
        });
    }
}
