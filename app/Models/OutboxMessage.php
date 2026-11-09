<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    use HasUuids;
    use MassPrunable;

    /**
     * отключаем стандартный updated_at, так как в таблице его нет
     */
    const UPDATED_AT = null;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'subject',
        'type',
        'aggregate_id',
        'payload',
        'headers',
        'attempts',
        'last_error',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('published_at', '<', now()->subDays(7));
    }
}
