<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => PaymentStatus::PENDING,
            // сумма по умолчанию совпадает с суммой заказа
            'amount' => fn (array $attributes) => Order::find($attributes['order_id'])->amount,
            'failure_reason' => null,
        ];
    }

    /**
     * состояние: Успешная оплата
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::SUCCEEDED,
            'failure_reason' => null,
        ]);
    }

    /**
     * состояние: Оплата отклонена
     */
    public function failed(string $reason = 'Card declined'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
            'failure_reason' => $reason,
        ]);
    }
}
