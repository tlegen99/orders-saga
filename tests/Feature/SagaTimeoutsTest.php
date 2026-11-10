<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxMessage;
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

it('зависший заказ отменяется по таймауту и возвращает товар', function () {
    // перемотка времени на 6 минут вперед
    $this->travel(6)->minutes();

    $this->artisan('saga:timeouts')
        ->expectsOutput('Отменено по таймауту: 1')
        ->assertSuccessful();

    $this->product->refresh();
    $order = $this->order->refresh();

    expect($order->status)->toBe(OrderStatus::CANCELLED)
        ->and($this->order->cancel_reason)->toBe('payment_timeout')
        ->and($this->product->stock)->toBe(10);
});

it('свежий заказ не трогаем', function () {
    $this->travel(2)->minutes();

    $this->artisan('saga:timeouts')->assertSuccessful();

    $this->product->refresh();
    $order = $this->order->refresh();

    expect($order->status)->toBe(OrderStatus::PAYMENT_PENDING);
});

it('подтверждённый заказ не трогаем и склад не изменился', function () {
    $this->order->update(['status' => OrderStatus::CONFIRMED]);
    $this->travel(6)->minutes();

    $this->artisan('saga:timeouts')->assertSuccessful();

    $this->product->refresh();
    $order = $this->order->refresh();

    expect($order->status)->toBe(OrderStatus::CONFIRMED)
        ->and($this->product->stock)->toBe(8)
        ->and($this->orderItem->quantity)->toBe(2);
});

it('поздний успех -> запрос на возврат', function () {
    $this->order->update(['status' => OrderStatus::CANCELLED]);
    $this->travel(6)->minutes();

    $this->handler->handle((string) Str::uuid(), 'PaymentSucceeded', [
        'order_uuid' => $this->order->uuid,
        'payment_id' => 123,
    ]);

    $order = $this->order->refresh();

    $outboxMessage = OutboxMessage::where('aggregate_id', $this->order->uuid)->first();

    expect($order->status)->toBe(OrderStatus::CANCELLED)
        ->and($outboxMessage->type)->toBe('RefundPayment');
});
