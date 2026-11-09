<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Orders\OrderSagaHandler;
use Illuminate\Support\Str;

beforeEach(function () {
    // подготовка: товар со stock = 8 (изначально было 10, 2 уже зарезервировали)
    $this->product = Product::factory()->create([
        'stock' => 8,
        'price' => 1000,
    ]);

    // заказ в статусе payment_pending
    $this->order = Order::factory()->create([
        'status' => OrderStatus::PAYMENT_PENDING,
    ]);

    // позиция заказа на 2 штуки этого товара
    $this->orderItem = OrderItem::factory()
        ->for($this->order)
        ->for($this->product)
        ->create(['quantity' => 2]);

    // настоящий обработчик саги из контейнера
    $this->handler = app(OrderSagaHandler::class);
});

it('успешная оплата подтверждает заказ и не трогает склад', function () {
    $this->handler->handle((string) Str::uuid(), 'PaymentSucceeded', [
        'order_uuid' => $this->order->uuid,
    ]);

    expect($this->order->refresh()->status)->toBe(OrderStatus::CONFIRMED)
        ->and($this->product->refresh()->stock)->toBe(8);
});

it('отказ отменяет заказ и возвращает товар', function () {
    $this->handler->handle((string) Str::uuid(), 'PaymentFailed', [
        'order_uuid' => $this->order->uuid,
        'reason' => 'Card declined',
    ]);

    expect($this->order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($this->product->refresh()->stock)->toBe(10)
        ->and($this->order->refresh()->cancel_reason)->toBe('Card declined');
});

it('дубль отказа возвращает товар один раз', function () {
    $messageId = (string) Str::uuid();
    $this->handler->handle($messageId, 'PaymentFailed', [
        'order_uuid' => $this->order->uuid,
        'reason' => 'Card declined',
    ]);
    $this->handler->handle($messageId, 'PaymentFailed', [
        'order_uuid' => $this->order->uuid,
        'reason' => 'Card declined',
    ]);

    expect($this->order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($this->product->refresh()->stock)->toBe(10)
        ->and($this->order->refresh()->cancel_reason)->toBe('Card declined');
});

it('запоздалый успех не воскрешает отменённый заказ', function () {
    $this->order->update(['status' => OrderStatus::CANCELLED]);

    $this->handler->handle((string) Str::uuid(), 'PaymentSucceeded', [
        'order_uuid' => $this->order->uuid
    ]);

    expect($this->order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($this->product->refresh()->stock)->toBe(8);
});
