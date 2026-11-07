<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'amount',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class, // каст в Enum оплат
            'amount' => 'integer',
        ];
    }

    /**
     * связь с заказом
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
