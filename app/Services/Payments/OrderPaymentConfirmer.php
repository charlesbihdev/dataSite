<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\Orders\OrderDispatchService;
use Illuminate\Support\Facades\DB;

/**
 * Turns a verified online-order payment into a dispatch, idempotently — shared by the storefront
 * return callback and the gateway webhooks so an order is paid-and-dispatched exactly once no matter
 * how many confirmations arrive. It re-verifies against the gateway (never trusts the caller), flips
 * payment_status → paid and fulfils in one committed step (invariant #11); the underlying
 * {@see OrderDispatchService::fulfillPaid} is itself guarded, so a double call can't send twice.
 */
class OrderPaymentConfirmer
{
    public function __construct(
        private readonly PaymentVerifier $verifier,
        private readonly OrderDispatchService $dispatch,
    ) {}

    public function confirm(Order $order): string
    {
        if ($order->payment_status !== Order::PAYMENT_AWAITING) {
            // Already resolved — report the settled state without touching money again.
            return $order->payment_status === Order::PAYMENT_PAID ? PaymentVerifier::PAID : PaymentVerifier::PENDING;
        }

        $result = $this->verifier->verify($order);

        if ($result === PaymentVerifier::PAID) {
            $claimed = DB::transaction(function () use ($order): bool {
                /** @var Order $locked */
                $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->payment_status !== Order::PAYMENT_AWAITING) {
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
            $order->update(['payment_status' => Order::PAYMENT_FAILED]);

            return PaymentVerifier::FAILED;
        }

        return PaymentVerifier::PENDING;
    }
}
