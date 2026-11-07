<?php

namespace App\Http\Requests;

use App\DTO\CreateOrderData;
use App\DTO\OrderItemData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toDto(): CreateOrderData
    {
        $items = array_map(function (array $item) {
            return new OrderItemData(
                (int) $item['product_id'],
                (int) $item['quantity']
            );
        }, $this->validated()['items']);

        return new CreateOrderData($items);
    }
}
