<?php

use App\Models\Order;
use App\Models\Product;

beforeEach(function () {
    $this->keyboard = Product::factory()->create(['price' => 1000, 'stock' => 10]);
    $this->monitor = Product::factory()->create(['price' => 5000, 'stock' => 2]);
});

it('создает заказ: возвращает 201, уменьшает stock, вычисляет amount как цена × количество', function () {
    $body = ['items' => [['product_id' => $this->keyboard->id, 'quantity' => 2]]];

    $this->postJson('/api/orders', $body, ['Idempotency-Key' => 'k1'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 2000) // 1000 * 2, цена из базы
        ->assertJsonPath('data.items.0.price', 1000); // снимок цены

    expect($this->keyboard->refresh()->stock)->toBe(8)
        ->and(Order::count())->toBe(1);
});

it('повтор с тем же ключом и телом отдает сохраненный ответ и не создает второй заказ', function () {
    $body = ['items' => [['product_id' => $this->keyboard->id, 'quantity' => 1]]];

    $first = $this->postJson('/api/orders', $body, ['Idempotency-Key' => 'k1'])->assertCreated();
    $second = $this->postJson('/api/orders', $body, ['Idempotency-Key' => 'k1'])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('data.uuid'))->toBe($first->json('data.uuid'))
        ->and(Order::count())->toBe(1)
        ->and($this->keyboard->refresh()->stock)->toBe(9); // списали один раз
});

it('считает повтором тело с другим порядком ключей', function () {
    $this->postJson('/api/orders',
        ['items' => [['product_id' => $this->keyboard->id, 'quantity' => 1]]],
        ['Idempotency-Key' => 'k1'],
    )->assertCreated();

    $this->postJson('/api/orders',
        ['items' => [['quantity' => 1, 'product_id' => $this->keyboard->id]]],
        ['Idempotency-Key' => 'k1'],
    )->assertHeader('Idempotent-Replayed', 'true');

    expect(Order::count())->toBe(1);
});

it('тот же ключ с другим телом возвращает 422 и не создает заказ', function () {
    $this->postJson('/api/orders',
        ['items' => [['product_id' => $this->keyboard->id, 'quantity' => 1]]],
        ['Idempotency-Key' => 'k1'],
    )->assertCreated();

    $this->postJson('/api/orders',
        ['items' => [['product_id' => $this->keyboard->id, 'quantity' => 2]]],
        ['Idempotency-Key' => 'k1'],
    )->assertStatus(422)
        ->assertHeaderMissing('Idempotent-Replayed');

    expect(Order::count())->toBe(1);
});

it('без Idempotency-Key возвращает 400', function () {
    $this->postJson('/api/orders', ['items' => [['product_id' => $this->keyboard->id, 'quantity' => 1]]])
        ->assertStatus(400);

    expect(Order::count())->toBe(0);
});

it('при нехватке товара возвращает 422 и не меняет stock ни одного товара', function () {
    $body = ['items' => [
        ['product_id' => $this->keyboard->id, 'quantity' => 1], // этого хватает
        ['product_id' => $this->monitor->id, 'quantity' => 99], // этого нет
    ]];

    $this->postJson('/api/orders', $body, ['Idempotency-Key' => 'k1'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Insufficient Stock');

    // клавиатура успела зарезервироваться, но откатилась вместе с savepoint
    expect($this->keyboard->refresh()->stock)->toBe(10)
        ->and($this->monitor->refresh()->stock)->toBe(2)
        ->and(Order::count())->toBe(0);

    // 422 бизнес-логики сохранен под ключом
    $this->postJson('/api/orders', $body, ['Idempotency-Key' => 'k1'])
        ->assertStatus(422)
        ->assertHeader('Idempotent-Replayed', 'true');
});

it('несуществующий товар возвращает 422 со списком missing_ids', function () {
    $this->postJson('/api/orders',
        ['items' => [['product_id' => 999999, 'quantity' => 1]]],
        ['Idempotency-Key' => 'k1'],
    )->assertStatus(422)
        ->assertJsonPath('missing_ids', [999999]);

    expect(Order::count())->toBe(0);
});

it('пустой список позиций не проходит валидацию', function () {
    $this->postJson('/api/orders', ['items' => []], ['Idempotency-Key' => 'k1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});
