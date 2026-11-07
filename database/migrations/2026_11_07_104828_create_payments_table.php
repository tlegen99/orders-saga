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
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            // одна оплата на один заказ (уникальный внешний ключ)
            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->restrictOnDelete(); // финансовые записи каскадом не удаляем

            $table->string('status', 32);          // каст в PaymentStatus Enum
            $table->bigInteger('amount');          // сумма в копейках
            $table->string('failure_reason')->nullable(); // причина сбоя, если статус failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
