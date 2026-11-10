<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Orders\OrderCanceller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('saga:timeouts')]
#[Description('Отмена зависших заказов по таймауту')]
class SagaTimeouts extends Command
{
    /**
     * Execute the console command.
     *
     * @throws \Throwable
     */
    public function handle(OrderCanceller $canceller): int
    {
        $paymentTimeout = config('saga.payment_timeout_minutes');

        $stuckOrderIds = Order::where('status', OrderStatus::PAYMENT_PENDING)
            ->where('updated_at', '<', now()->subMinutes($paymentTimeout))
            ->orderBy('updated_at')
            ->limit(100)
            ->pluck('id');

        $canceledCount = 0;
        foreach ($stuckOrderIds as $orderId) {
            $isCanceled = DB::transaction(function () use ($orderId, $canceller) {
                // блокируем заказ
                $order = Order::lockForUpdate()->find($orderId);
                // перепроверка на то что есть ли заказ + статус мог измениться поэтому проверяем еще раз
                if (! $order || $order->status !== OrderStatus::PAYMENT_PENDING) {
                    return false;
                }

                // отмена
                $canceller->cancel($order, 'payment_timeout');

                return true;
            });

            if ($isCanceled) {
                $canceledCount++;
            }
        }

        $this->info("Отменено по таймауту: {$canceledCount}");

        return self::SUCCESS;
    }
}
