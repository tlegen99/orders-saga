<?php

namespace App\DTO;

final readonly class CreateOrderData
{
    /** @param list<OrderItemData> $items */
    public function __construct(
        public array $items,
    ) {}
}
