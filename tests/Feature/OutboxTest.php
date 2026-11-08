<?php

use App\Messaging\MessageBus;
use App\Models\OutboxMessage;
use App\Models\Product;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeMessageBus;

beforeEach(function () {
    // подменяем настоящую шину фейком: команда получит его через контейнер
    $this->bus = new FakeMessageBus;
    $this->app->instance(MessageBus::class, $this->bus);

    $this->product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
});

function createOrder(object $test, int $productId, int $quantity): TestResponse
{
    return $test->postJson('/api/orders',
        ['items' => [['product_id' => $productId, 'quantity' => $quantity]]],
        ['Idempotency-Key' => 'key-'.uniqid()],
    );
}

it('создание заказа пишет в outbox ровно одно сообщение ChargePayment', function () {
    $uuid = createOrder($this, $this->product->id, 2)->assertCreated()->json('data.uuid');

    expect(OutboxMessage::count())->toBe(1);

    $message = OutboxMessage::first();
    expect($message->subject)->toBe('payments.charge')
        ->and($message->type)->toBe('ChargePayment')
        ->and($message->aggregate_id)->toBe($uuid)
        ->and($message->payload)->toEqual(['order_uuid' => $uuid, 'amount' => 2000]) // toEqual: jsonb меняет порядок ключей
        ->and($message->published_at)->toBeNull()
        // до запуска почтальона в шину ничего не ушло
        ->and($this->bus->published)->toBeEmpty();
});

it('при нехватке товара сообщение в outbox не появляется', function () {
    createOrder($this, $this->product->id, 99)->assertStatus(422);

    // заказ и письмо откатились вместе - это и есть смысл outbox
    expect(OutboxMessage::count())->toBe(0);
});

it('outbox:publish отправляет сообщение в шину и ставит published_at, повтор ничего не шлет', function () {
    $uuid = createOrder($this, $this->product->id, 1)->json('data.uuid');
    $message = OutboxMessage::first();

    $this->artisan('outbox:publish --once')->assertSuccessful();

    expect($this->bus->published)->toHaveCount(1)
        ->and($this->bus->published[0]['subject'])->toBe('payments.charge')
        ->and($this->bus->published[0]['payload']['order_uuid'])->toBe($uuid)
        // message_id = id строки outbox, по нему консьюмер будет отсекать дубли
        ->and($this->bus->published[0]['headers']['message_id'])->toBe($message->id)
        ->and($message->refresh()->published_at)->not->toBeNull();

    // второй запуск: неотправленных писем нет
    $this->artisan('outbox:publish --once')->assertSuccessful();
    expect($this->bus->published)->toHaveCount(1);
});

it('если брокер недоступен, сообщение остается неотправленным и считает попытки', function () {
    createOrder($this, $this->product->id, 1);
    $this->bus->shouldFail = true;

    $this->artisan('outbox:publish --once')->assertSuccessful();

    $message = OutboxMessage::first();
    expect($message->published_at)->toBeNull()
        ->and($message->attempts)->toBe(1)
        ->and($message->last_error)->toBe('Broker is unavailable');

    // брокер поднялся - следующий запуск доставит письмо
    $this->bus->shouldFail = false;
    $this->artisan('outbox:publish --once')->assertSuccessful();

    expect($this->bus->published)->toHaveCount(1)
        ->and($message->refresh()->published_at)->not->toBeNull();
});
