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
        Schema::create('processed_messages', function (Blueprint $table) {
            $table->string('consumer', 64);
            $table->uuid('message_id');
            $table->timestamp('processed_at')->useCurrent(); // проставляется автоматически базой

            // составной первичный ключ для защиты от дубликатов у разных консьюмеров
            $table->primary(['consumer', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
    }
};
