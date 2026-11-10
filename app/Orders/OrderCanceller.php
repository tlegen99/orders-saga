<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;

final readonly class OrderCanceller
{
    public function cancel(Order $order, string $reason): void
    {
        // один порядок блокировок для всех - защита от deadlock
        foreach ($order->items->sortBy('product_id') as $item) {
            Product::whereKey($item->product_id)->increment('stock', $item->quantity);
        }

        $order->update([
            'status' => OrderStatus::CANCELLED,
            'cancel_reason' => $reason,
        ]);
    }
}
