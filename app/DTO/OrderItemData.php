<?php

namespace App\DTO;

final readonly class OrderItemData
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}
