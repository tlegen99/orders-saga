<?php

namespace App\Console\Commands;

use Basis\Nats\Client;
use Illuminate\Console\Command;

class NatsSetup extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nats:setup';

    /**
     * @var string
     */
    protected $description = 'Настройка инфраструктуры NATS JetStream (создание стримов и конфигураций)';

    /**
     * Execute the console command.
     */
    public function handle(Client $client): int
    {
        $this->info('Проверка инфраструктуры NATS...');

        // объект стрима
        $stream = $client->getApi()->getStream('SAGA');

        // проверяем не создан ли стрим
        if ($stream->exists()) {
            $this->warn('Стрим [SAGA] уже существует. Пропускаем создание.');

            return Command::SUCCESS;
        }

        $this->info('Стрим [SAGA] не найден. Начинаем настройку...');

        // обрабатываем только такие Subjects
        $stream->getConfiguration()->setSubjects([
            'payments.*',
            'orders.*',
        ]);

        // запрос на создание
        $stream->create();

        $this->info('Стрим [SAGA] успешно создан с subjects: payments.*, orders.*');

        return Command::SUCCESS;
    }
}
