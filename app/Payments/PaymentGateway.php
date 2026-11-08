<?php


namespace App\Payments;

interface PaymentGateway
{
    public function charge(string $idempotencyKey, int $amount): ChargeResult;
}
