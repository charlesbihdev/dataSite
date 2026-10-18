<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Order;
use App\Services\Orders\OrderDispatchService;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Services\Payments\PaymentVerifier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Shared actions for order payment verification and dispatch retry.
 * Reused by both Agent and Subagent order controllers so business logic,
 * idempotency guards, and user-facing feedback stay unified.
 */
trait HandlesOrderActions
{
    /**
     * Re-dispatch a failed order.
     */
    protected function performOrderRetry(Order $order, OrderDispatchService $dispatch): RedirectResponse
    {
        if ($order->status !== Order::STATUS_FAILED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only failed orders can be retried.']);

            return back();
        }

        try {
            $dispatch->redispatch($order);
        } catch (InsufficientBalanceException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Insufficient wallet balance to retry this order. Top up and try again.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->reference} is being re-sent."]);

        return back();
    }

    /**
     * Verify payment with the payment gateway and dispatch if confirmed.
     */
    protected function performOrderPaymentVerification(Order $order, OrderPaymentConfirmer $confirmer): RedirectResponse
    {
        if ($order->payment_status !== Order::PAYMENT_AWAITING) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only orders awaiting payment can be verified.']);

            return back();
        }

        $toast = match ($confirmer->confirm($order)) {
            PaymentVerifier::PAID => ['type' => 'success', 'message' => "Payment confirmed for {$order->reference}. Bundle dispatched."],
            PaymentVerifier::FAILED => ['type' => 'error', 'message' => "{$order->reference}: the payment failed. Order marked failed."],
            default => ['type' => 'info', 'message' => "{$order->reference}: payment not confirmed yet. Try again shortly."],
        };

        Inertia::flash('toast', $toast);

        return back();
    }
}

