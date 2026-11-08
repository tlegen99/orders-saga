<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Payments\ChargePaymentHandler;
use Illuminate\Support\Str;

beforeEach(function () {
    $order = Order::factory()->paymentPending()->create(['amount' => 450000]);
    $this->payload = ['order_uuid' => $order->uuid, 'amount' => $order->amount];
    $this->handler = app(ChargePaymentHandler::class);
});

it('успешная оплата сохраняет payment и отправляет PaymentSucceeded', function () {
    $messageId = (string) Str::uuid();
    // действие
    $this->handler->handle($messageId, $this->payload);

    // проверка
    expect(Payment::count())->toBe(1)
        ->and(Payment::first()->status)->toBe(PaymentStatus::SUCCEEDED)
        ->and(OutboxMessage::where('type', 'PaymentSucceeded')->count())->toBe(1);
});

it('повторное сообщение не создаёт вторую оплату и второй ответ', function () {
    $messageId = (string) Str::uuid();
    // действие
    $this->handler->handle($messageId, $this->payload);
    $this->handler->handle($messageId, $this->payload);

    // проверка
    expect(Payment::count())->toBe(1)
        ->and(Payment::first()->status)->toBe(PaymentStatus::SUCCEEDED)
        ->and(OutboxMessage::where('type', 'PaymentSucceeded')->count())->toBe(1);
});

it('отказ шлюза сохраняет failed-оплату с причиной и отправляет PaymentFailed', function () {
    $order = Order::factory()->paymentPending()->create(['amount' => 131300]);
    $payload = ['order_uuid' => $order->uuid, 'amount' => $order->amount];
    $handler = app(ChargePaymentHandler::class);

    $messageId = (string) Str::uuid();
    // действие
    $handler->handle($messageId, $payload);

    // проверка
    expect(Payment::count())->toBe(1);

    $payment = Payment::first();
    expect($payment->status)->toBe(PaymentStatus::FAILED)
        ->and($payment->failure_reason)->not->toBeEmpty()
        ->and(OutboxMessage::where('type', 'PaymentFailed')->count())->toBe(1)
        ->and(OutboxMessage::where('type', 'PaymentSucceeded')->count())->toBe(0);

});
