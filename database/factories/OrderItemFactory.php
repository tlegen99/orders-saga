<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(), // новый товар на каждую позицию - без конфликта unique(order_id, product_id)
            'quantity' => $this->faker->numberBetween(1, 5),
            // снимок цены: замыкание вызывается после того, как product_id уже определён
            'price' => fn (array $attributes) => Product::find($attributes['product_id'])->price,
        ];
    }
}
