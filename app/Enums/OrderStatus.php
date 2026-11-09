<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAYMENT_PENDING = 'payment_pending';
    case PAID = 'paid';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function canTransitionTo(OrderStatus $to): bool
    {
        $allowedTransitions = match ($this) {
            self::PENDING, self::PAID, self::CONFIRMED, self::CANCELLED => [],
            self::PAYMENT_PENDING => [self::CONFIRMED, self::CANCELLED],
        };

        return in_array($to, $allowedTransitions, true);
    }
}
