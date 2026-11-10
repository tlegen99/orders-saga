<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class OrderSagaHandler
{
    public function __construct(
        private OrderCanceller $canceller,
        private Outbox $outbox,
    ) {}

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
                if ($type === 'PaymentSucceeded' && $order->status === OrderStatus::CANCELLED) {
                    $this->outbox->record('payments.refund', 'RefundPayment', $order->uuid, [
                        'order_uuid' => $order->uuid,
                        'payment_id' => $payload['payment_id'] ?? null,
                    ]);

                    Log::warning('оплата пришла после отмены, отправлен запрос на возврат');
                }

                Log::warning("Order transition from {$order->status->value} to {$newStatus->value} is not allowed", [
                    'order_uuid' => $order->uuid,
                    'new_status' => $newStatus->value,
                ]);

                return;
            }

            if ($type === 'PaymentSucceeded') {
                $order->update(['status' => OrderStatus::CONFIRMED]);
            } else {
                $this->canceller->cancel($order, $payload['reason'] ?? 'payment_failed');
            }
        });
    }
}
