<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Outbox;
use Illuminate\Support\Facades\DB;

final readonly class ChargePaymentHandler
{
    public function __construct(
        private PaymentGateway $gateway,
        private Outbox $outbox
    ) {}

    public function handle(string $messageId, array $payload): void
    {
        DB::transaction(function () use ($messageId, $payload) {
            $inserted = DB::table('processed_messages')->insertOrIgnore([
                'consumer' => 'payments',
                'message_id' => $messageId,
            ]);

            if ($inserted === 0) {
                return;
            }

            $orderUuid = $payload['order_uuid'];
            $amount = (int) $payload['amount'];

            $order = Order::where('uuid', $orderUuid)->firstOrFail();

            $result = $this->gateway->charge($orderUuid, $amount);

            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $amount,
                'status' => $result->success ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED,
                'failure_reason' => $result->failureReason,
            ]);

            if ($result->success) {
                $this->outbox->record(
                    'orders.payment_succeeded',
                    'PaymentSucceeded',
                    $orderUuid,
                    [
                        'order_uuid' => $orderUuid,
                        'payment_id' => $payment->id,
                    ]
                );
            } else {
                $this->outbox->record(
                    'orders.payment_failed',
                    'PaymentFailed',
                    $orderUuid,
                    [
                        'order_uuid' => $orderUuid,
                        'reason' => $result->failureReason,
                    ]
                );
            }
        });
    }
}
