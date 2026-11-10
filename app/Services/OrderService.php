<?php

namespace App\Services;

use App\DTO\CreateOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OrderService
{
    public function __construct(private Outbox $outbox) {}

    public function create(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data) {
            $productIds = array_map(function ($item) {
                return $item->productId;
            }, $data->items);

            // загрузка товаров с блокировкой
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $missingIds = array_values(array_diff($productIds, $products->keys()->all()));

            if ($missingIds !== []) {
                throw new ProductNotFoundException($missingIds);
            }

            $totalAmount = 0;
            $itemsToCreate = [];
            foreach ($data->items as $itemData) {
                $product = $products->get($itemData->productId);

                // если кол-во товара меньше запрошеного
                if ($product->stock < $itemData->quantity) {
                    throw new InsufficientStockException($itemData->productId, $itemData->quantity, $product->stock);
                }

                $product->stock = $product->stock - $itemData->quantity;
                $product->save();

                $itemPrice = $product->price;
                $totalAmount += $itemPrice * $itemData->quantity;

                $itemsToCreate[] = [
                    'product_id' => $itemData->productId,
                    'quantity' => $itemData->quantity,
                    'price' => $itemPrice,
                ];
            }

            // создаем заказ
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'status' => OrderStatus::PAYMENT_PENDING,
                'amount' => $totalAmount,
            ]);

            // масс создание позиций
            $order->items()->createMany($itemsToCreate);

            $this->outbox->record('payments.charge', 'ChargePayment', $order->uuid, [
                'order_uuid' => $order->uuid,
                'amount' => $order->amount,
            ]);

            return $order->load('items');
        });
    }
}
