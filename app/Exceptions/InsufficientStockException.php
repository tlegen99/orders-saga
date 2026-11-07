<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requested,
        public readonly int $available
    ) {
        parent::__construct('Insufficient stock in the warehouse');
    }
}
