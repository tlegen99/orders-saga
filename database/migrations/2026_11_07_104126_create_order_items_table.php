<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            // позиции без заказа не нужны — cascade
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // нельзя удалить товар, который есть в заказах — restrict
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->integer('quantity');
            $table->bigInteger('price'); // снимок цены на момент покупки в копейках
            $table->timestamps();

            // один товар — одна строка в конкретном заказе
            $table->unique(['order_id', 'product_id']);
        });

        // защита базы от нулевого или отрицательного количества
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
