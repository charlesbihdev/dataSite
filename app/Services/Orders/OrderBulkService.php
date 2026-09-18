<?php

namespace App\Services\Orders;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Order;
use App\Services\Payments\PaymentVerifier;

/**
 * Applies a bulk action to many selected orders and returns a human summary. Each action scopes to
 * the orders it can legally act on, so a mixed selection is always safe:
 *   Regular — verify / mark-verified / delete  (awaiting only)
 *   Agent   — sync (processing) / retry (failed or held-pending) / apply-status
 * Money always moves through the dispatch/settlement services, never a raw status write.
 */
class OrderBulkService
{
    public function __construct(
        private readonly PaymentVerifier $verifier,
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
                        ->where('status', Order::STATUS_PENDING)
                        ->where('payment_status', Order::PAYMENT_PAID)
                        ->whereNull('upstream_request_id'));
            })
            ->get();
        $done = 0;
        $short = 0;

        foreach ($orders as $order) {
            try {
                $this->dispatch->redispatch($order);
                $done++;
            } catch (InsufficientBalanceException) {
                $short++;
            }
        }

        return "Retried {$done} order(s)".($short > 0 ? ", {$short} skipped (insufficient balance)." : '.');
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
     * Regular-side actions on awaiting orders: verify (gateway-branched), mark-verified (manual
     * confirm + dispatch), delete.
     *
     * @param  array<int, int>  $ids
     */
    private function awaiting(string $action, array $ids): string
    {
        $orders = Order::query()->whereIn('id', $ids)->where('payment_status', Order::PAYMENT_AWAITING)->get();
        $done = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if ($action === 'delete') {
                $order->delete();
                $done++;

                continue;
            }

            if ($action === 'mark-verified') {
                $order->update(['payment_status' => Order::PAYMENT_PAID]);
                $this->dispatch->fulfillPaid($order);
                $done++;

                continue;
            }

            // verify → check the gateway and branch per order
            match ($this->verifier->verify($order)) {
                PaymentVerifier::PAID => tap($done++, function () use ($order): void {
                    $order->update(['payment_status' => Order::PAYMENT_PAID]);
                    $this->dispatch->fulfillPaid($order);
                }),
                PaymentVerifier::FAILED => tap($failed++, fn () => $order->update(['payment_status' => Order::PAYMENT_FAILED])),
                default => $skipped++,
            };
        }

        return match ($action) {
            'delete' => "{$done} order(s) deleted.",
            'mark-verified' => "{$done} order(s) verified & dispatched.",
            default => "Verified & dispatched {$done}, payment failed {$failed}, still awaiting {$skipped}.",
        };
    }
}
