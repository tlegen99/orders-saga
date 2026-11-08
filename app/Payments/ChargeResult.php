<?php

namespace App\Payments;

/**
 * DTO для возврата результата авторизации платежа
 */
final readonly class ChargeResult
{
    public function __construct(
        public bool $success,
        public ?string $failureReason = null
    ) {}
}
