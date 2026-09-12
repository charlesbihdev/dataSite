<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Asks the payment gateway whether a storefront order's money actually cleared, so the admin
 * never has to guess. Returns one of PAID / FAILED / PENDING and the caller branches on it:
 * PAID → dispatch the bundle, FAILED → mark the order failed, PENDING → leave it and retry later.
 *
 * The storefront checkout (Paystack/momo) isn't integrated yet, so this can't confirm anything
 * and returns PENDING. When that gateway lands, implement the real verify call here — the admin
 * action and its tests already branch on the three outcomes, so nothing else has to change.
 */
class PaymentVerifier
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public function verify(Order $order): string
    {
        return self::PENDING;
    }
}
