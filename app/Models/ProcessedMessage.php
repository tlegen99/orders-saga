<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Отметки об обработанных сообщениях (защита консьюмеров от повторной доставки).
 * Модель нужна только для чистки старых записей: обработчики пишут в таблицу
 * через DB::table()->insertOrIgnore(). Первичный ключ составной (consumer, message_id),
 * колонки id и updated_at нет - поэтому отключаем автоинкремент и timestamps.
 */
final class ProcessedMessage extends Model
{
    use MassPrunable;

    public $incrementing = false;

    public $timestamps = false;

    public function prunable(): Builder
    {
        return self::where('processed_at', '<', now()->subDays(30));
    }
}
