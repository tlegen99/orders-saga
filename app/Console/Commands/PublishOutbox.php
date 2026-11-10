<?php

namespace App\Console\Commands;

use App\Messaging\MessageBus;
use App\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublishOutbox extends Command
{
    private const MAX_ATTEMPTS = 10;
    private const MAX_BACKOFF_SECONDS = 300;

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

            if ($sent > 0 || $this->option('once')) {
                $this->info("Отправлено: {$sent}");
            }

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
                ->whereNull('failed_at')
                ->where(function ($q) {
                    $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                })
                ->orderBy('created_at')
                ->orderBy('id') // если created_at совпали то поможет еще по одному полю
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
                    if ($message->attempts >= self::MAX_ATTEMPTS) {
                        $message->failed_at = now();
                        Log::error('Не удалось опубликовать сообщение из Outbox после исчерпания попыток', [
                            'message_id' => $message->id,
                            'subject' => $message->subject,
                            'error' => $e->getMessage(),
                        ]);
                    } else {
                        $pause = min(2 ** $message->attempts, self::MAX_BACKOFF_SECONDS);
                        $message->next_attempt_at = now()->addSeconds($pause);
                    }

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
