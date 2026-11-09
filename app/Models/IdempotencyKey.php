<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use MassPrunable;

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now());
    }
}
