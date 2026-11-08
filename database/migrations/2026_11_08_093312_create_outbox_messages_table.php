<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            // message_id для дедупликации у консьюмера
            $table->uuid('id')->primary();
            // куда публиковать (например, payments.charge)
            $table->string('subject');
            // консьюмер по нему выбирает обработчик (например, ChargePayment)
            $table->string('type');
            // uuid заказа, удобно искать при отладке
            $table->string('aggregate_id');
            // данные сообщения
            $table->jsonb('payload');
            // сюда позже ляжет correlation id
            $table->jsonb('headers')->nullable();
            // сколько раз пытались отправить
            $table->smallInteger('attempts')->default(0);
            // последняя ошибка отправки
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // timestamp, nullable - null = ещё не отправлено
            $table->timestamp('published_at')->nullable();

            $table->index('aggregate_id');
        });

        // частичный по не отправленный, нужен именно такой так как отправленных может быть миллионы строк
        DB::statement('CREATE INDEX outbox_unpublished_idx ON outbox_messages (created_at) WHERE published_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
