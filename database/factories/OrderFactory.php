<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'status' => OrderStatus::PENDING, // детерминированный статус, остальные - через state
            'amount' => $this->faker->numberBetween(500, 50000) * 100, // сумма в копейках
        ];
    }

    /**
     * Состояние: Заказ завис в статусе оплаты
     */
    public function paymentPending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PAYMENT_PENDING,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CONFIRMED,
        ]);
    }

    public function cancelled(string $reason = 'payment_failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CANCELLED,
            'cancel_reason' => $reason,
        ]);
    }
}
