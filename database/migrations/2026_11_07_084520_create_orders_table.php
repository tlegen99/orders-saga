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
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique(); // публичный ID для API - Outbox
            $table->string('status', 32);   // статус заказа (cast в Enum)
            $table->bigInteger('amount');   // сумма в копейках

            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            // составной индекс для Saga Timeouts (поиск зависших по статусу и времени изменения)
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
