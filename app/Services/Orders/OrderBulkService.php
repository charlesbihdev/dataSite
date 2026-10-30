<?php

namespace App\Services\Orders;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentNotConfirmedException;
use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Order;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Services\Payments\PaymentVerifier;

/**
 * Applies a bulk action to many selected orders and returns a human summary. Each action scopes to
 * the orders it can legally act on, so a mixed selection is always safe:
 *   Regular — verify (awaiting, failed or expired) / mark-verified / delete  (awaiting only)
 *   Agent   — sync (processing) / retry (failed or held-pending) / apply-status
 * Money always moves through the dispatch/settlement services, never a raw status write.
 */
class OrderBulkService
{
    public function __construct(
        private readonly OrderPaymentConfirmer $confirmer,
        private readonly OrderDispatchService $dispatch,
        private readonly OrderSettlementService $settlement,
    ) {}

    /**
     * @param  array<int, int>  $ids
     */
    public function apply(string $action, array $ids, ?string $status = null): string
    {
        return match ($action) {
            'sync' => $this->sync($ids),
            'retry' => $this->retry($ids),
            'apply-status' => $this->applyStatus($ids, (string) $status),
            'verify' => $this->verify($ids),
            default => $this->awaiting($action, $ids),
        };
    }

    /** @param array<int, int> $ids */
    private function sync(array $ids): string
    {
        $processing = Order::query()
            ->whereIn('id', $ids)
            ->where('status', Order::STATUS_PROCESSING)
            ->whereNotNull('upstream_request_id')
            ->pluck('id');

        $processing->each(fn (int $id) => PollUpstreamOrderStatus::dispatch($id));

        return $processing->count().' status poll(s) queued.';
    }

    /**
     * Retry both failed orders and orders held PENDING because the supplier was unreachable
     * (paid, money reserved, never accepted upstream). A PENDING order still awaiting customer
     * payment (storefront) is excluded — it is not ours to dispatch yet.
     *
     * @param  array<int, int>  $ids
     */
    private function retry(array $ids): string
    {
        $orders = Order::query()
            ->whereIn('id', $ids)
            ->where(function ($query): void {
                $query->where('status', Order::STATUS_FAILED)
                    ->orWhere(fn ($held) => $held
                        ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_VERIFYING])
                        ->where('payment_status', Order::PAYMENT_PAID)
                        ->whereNull('upstream_request_id'));
            })
            ->get();
        $done = 0;
        $short = 0;
        $unpaid = 0;

        foreach ($orders as $order) {
            try {
                $this->dispatch->redispatch($order);
                $done++;
            } catch (InsufficientBalanceException) {
                $short++;
            } catch (PaymentNotConfirmedException) {
                $unpaid++;
            }
        }

        return "Retried {$done} order(s)"
            .($short > 0 ? ", {$short} skipped (insufficient balance)" : '')
            .($unpaid > 0 ? ", {$unpaid} skipped (payment not confirmed)" : '').'.';
    }

    /**
     * Apply a target status to selected orders, routing the money-affecting ones through the
     * settlement services (DBH's update_status, made safe for our cascade):
     *   failed → reverse (refund + reverse earnings) · refunded → refund · completed → credit earnings
     *   pending / processing → plain label change (no money moves)
     *
     * @param  array<int, int>  $ids
     */
    private function applyStatus(array $ids, string $status): string
    {
        $orders = Order::query()->whereIn('id', $ids)->get();
        $done = 0;

        foreach ($orders as $order) {
            if ($order->status === $status) {
                continue;
            }

            match ($status) {
                Order::STATUS_FAILED => $this->settlement->reverse($order, 'Marked failed by admin'),
                Order::STATUS_REFUNDED => $this->settlement->refund($order, 'Marked refunded by admin'),
                Order::STATUS_COMPLETED => $this->settlement->completeManually($order),
                default => $order->update(['status' => $status]), // pending / processing
            };
            $done++;
        }

        return "{$done} order(s) set to {$status}.";
    }

    /**
     * Gateway-verify unpaid storefront orders (awaiting, failed or expired) through the shared
     * confirmer — only a real gateway success dispatches.
     *
     * @param  array<int, int>  $ids
     */
    private function verify(array $ids): string
    {
        $orders = Order::query()
            ->whereIn('id', $ids)
            ->where('channel', Order::CHANNEL_ONLINE)
            ->whereIn('payment_status', Order::VERIFIABLE_PAYMENTS)
            ->get();
        $tally = [PaymentVerifier::PAID => 0, PaymentVerifier::FAILED => 0, PaymentVerifier::PENDING => 0];

        foreach ($orders as $order) {
            $tally[$this->confirmer->confirm($order)]++;
        }

        return "Verified & dispatched {$tally[PaymentVerifier::PAID]}, payment failed {$tally[PaymentVerifier::FAILED]}, still unconfirmed {$tally[PaymentVerifier::PENDING]}.";
    }

    /**
     * Regular-side manual actions on awaiting orders: mark-verified (confirm + dispatch), delete.
     *
     * @param  array<int, int>  $ids
     */
    private function awaiting(string $action, array $ids): string
    {
        $orders = Order::query()->whereIn('id', $ids)->where('payment_status', Order::PAYMENT_AWAITING)->get();

        foreach ($orders as $order) {
            if ($action === 'delete') {
                $order->delete();

                continue;
            }

            $order->update(['payment_status' => Order::PAYMENT_PAID]);
            $this->dispatch->fulfillPaid($order);
        }

        return $action === 'delete'
            ? "{$orders->count()} order(s) deleted."
            : "{$orders->count()} order(s) verified & dispatched.";
    }
}
