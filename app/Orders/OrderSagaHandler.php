<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class OrderSagaHandler
{
    public function handle(string $messageId, string $type, array $payload): void
    {
        DB::transaction(function () use ($messageId, $type, $payload) {
            $inserted = DB::table('processed_messages')
                ->insertOrIgnore(['message_id' => $messageId, 'consumer' => 'orders']);

            if ($inserted === 0) {
                return;
            }

            $order = Order::where('uuid', $payload['order_uuid'])->lockForUpdate()->firstOrFail();

            $newStatus = match ($type) {
                'PaymentSucceeded' => OrderStatus::CONFIRMED,
                'PaymentFailed' => OrderStatus::CANCELLED,
                default => null,
            };

            if ($newStatus === null) {
                return;
            }

            if ($order->status->canTransitionTo($newStatus) === false) {
                Log::warning("Order transition from {$order->status->value} to {$newStatus->value} is not allowed", [
                    'order_uuid' => $order->uuid,
                    'new_status' => $newStatus->value,
                ]);
                return;
            }

            if ($type === 'PaymentFailed') {
                // один порядок блокировок для всех - защита от deadlock
                foreach ($order->items->sortBy('product_id') as $item) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'status' => $newStatus,
                'cancel_reason' => $type === 'PaymentFailed' ? ($payload['reason'] ?? null) : null,
            ]);
        });
    }
}
