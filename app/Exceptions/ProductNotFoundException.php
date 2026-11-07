<?php

namespace App\Exceptions;

use Exception;

class ProductNotFoundException extends Exception
{
    public function __construct(public readonly array $missingIds)
    {
        parent::__construct('Some products were not found in the database.');
    }
}
