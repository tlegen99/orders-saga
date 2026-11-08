<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            // уникальный ключ, защита от повторов
            $table->string('key', 255)->unique();
            // sha256 отпечаток запроса
            $table->char('request_hash', 64);
            // данные ответа (заполняются в конце)
            $table->smallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();

            $table->timestamps();
            // время жизни ключа и индекс для быстрой очистки cron-задачей
            $table->timestamp('expires_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
