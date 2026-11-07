<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAYMENT_PENDING = 'payment_pending';
    case PAID = 'paid';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
