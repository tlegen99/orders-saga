<?php

namespace App\Payments;

class FakePaymentGateway implements PaymentGateway
{
    public function charge(string $idempotencyKey, int $amount): ChargeResult
    {
        $rubles = intdiv($amount, 100);

        if ($rubles % 100 === 13) {
            return new ChargeResult(false, 'Payment rejected: Unlucky amount (ends with 13)');
        }

        return new ChargeResult(true);
    }
}
