<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\Orders\OrderDispatchService;
use Illuminate\Support\Facades\DB;

/**
 * Turns a verified online-order payment into a dispatch, idempotently — shared by the storefront
 * return callback, the gateway webhooks, the admin Verify buttons and the pending-payment sweep, so
 * an order is paid-and-dispatched exactly once no matter how many confirmations arrive. It
 * re-verifies against the gateway (never trusts the caller), flips payment_status → paid and
 * fulfils in one committed step (invariant #11); the underlying
 * {@see OrderDispatchService::fulfillPaid} is itself guarded, so a double call can't send twice.
 * Failed/expired orders stay verifiable: only a real gateway `success` moves them to paid.
 */
class OrderPaymentConfirmer
{
    public function __construct(
        private readonly PaymentVerifier $verifier,
        private readonly OrderDispatchService $dispatch,
    ) {}

    public function confirm(Order $order): string
    {
        if (! in_array($order->payment_status, Order::VERIFIABLE_PAYMENTS, true)) {
            // Already resolved — report the settled state without touching money again.
            return $order->payment_status === Order::PAYMENT_PAID ? PaymentVerifier::PAID : PaymentVerifier::PENDING;
        }

        $result = $this->verifier->verify($order);

        if ($result === PaymentVerifier::PAID) {
            $claimed = DB::transaction(function () use ($order): bool {
                /** @var Order $locked */
                $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                if (! in_array($locked->payment_status, Order::VERIFIABLE_PAYMENTS, true)) {
                    return false;
                }
                $locked->update(['payment_status' => Order::PAYMENT_PAID]);

                return true;
            });

            if ($claimed) {
                $this->dispatch->fulfillPaid($order->refresh());
            }

            return PaymentVerifier::PAID;
        }

        if ($result === PaymentVerifier::FAILED) {
            $this->transition($order, [Order::PAYMENT_AWAITING, Order::PAYMENT_EXPIRED], Order::PAYMENT_FAILED);

            return PaymentVerifier::FAILED;
        }

        return PaymentVerifier::PENDING;
    }

    /**
     * Mark a still-awaiting order expired. Conditional write, so a webhook that paid it a moment
     * earlier always wins.
     */
    public function expire(Order $order): bool
    {
        return $this->transition($order, [Order::PAYMENT_AWAITING], Order::PAYMENT_EXPIRED);
    }

    /**
     * @param  list<string>  $from
     */
    private function transition(Order $order, array $from, string $to): bool
    {
        $changed = Order::query()
            ->whereKey($order->getKey())
            ->whereIn('payment_status', $from)
            ->update(['payment_status' => $to, 'updated_at' => now()]) > 0;

        if ($changed) {
            $order->payment_status = $to;
            $order->syncOriginalAttribute('payment_status');
        }

        return $changed;
    }
}
