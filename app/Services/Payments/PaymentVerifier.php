<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Asks the payment gateway whether an online order's money actually cleared, so no one has to guess.
 * Returns one of PAID / FAILED / PENDING and every caller (admin verify, bulk verify, storefront
 * callback, webhook) branches on it: PAID → dispatch the bundle, FAILED → mark failed, PENDING →
 * leave it awaiting and retry later. The verified amount is checked against the frozen
 * customer_price — a mismatch is treated as unconfirmed (PENDING), never PAID.
 */
class PaymentVerifier
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public function __construct(
        private readonly PaystackClient $paystack,
        private readonly MoolreClient $moolre,
    ) {}

    public function verify(Order $order): string
    {
        $reference = (string) ($order->gateway_reference ?: $order->reference);
        if ($order->gateway === null || $reference === '') {
            return self::PENDING; // no gateway payment was ever started for this order
        }

        $gateway = PaymentGateway::forGateway($order->gateway);
        if ($gateway === null) {
            return self::PENDING;
        }

        $verified = $order->gateway === PaymentGateway::MOOLRE
            ? $this->moolre->verify($gateway, $reference)
            : $this->paystack->verify($gateway, $reference);

        if (! $verified['ok']) {
            return self::PENDING;
        }

        $tx = $verified['tx'];

        return match ($tx['status']) {
            'success' => $this->confirmAmount($order, $tx),
            'failed' => self::FAILED,
            default => self::PENDING,
        };
    }

    /**
     * @param  array{amount_minor?: int, currency?: string}  $tx
     */
    private function confirmAmount(Order $order, array $tx): string
    {
        $expectedMinor = (int) round((float) $order->customer_price * 100);

        if (abs((int) ($tx['amount_minor'] ?? 0) - $expectedMinor) > 1) {
            Log::warning('Order payment amount mismatch', ['order' => $order->reference, 'expected' => $expectedMinor, 'got' => $tx['amount_minor'] ?? 0]);

            return self::PENDING;
        }

        return self::PAID;
    }
}
