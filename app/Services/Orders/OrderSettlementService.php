<?php

namespace App\Services\Orders;

use App\Models\Earning;
use App\Models\Order;
use App\Services\Databundleshub\UpstreamOrderResult;
use Illuminate\Support\Facades\DB;

/**
 * Turns a terminal upstream outcome into settled money. Both entry points are idempotent
 * (guarded on the locked order's status) so a repeated poll or retry can't double-settle.
 */
class OrderSettlementService
{
    public const TXN_REFUND = 'order_refund';

    /**
     * Order delivered: freeze the real upstream cost, mark completed, and credit the
     * pending earnings so they become withdrawable (ARCHITECTURE §3 step 6).
     */
    public function settle(Order $order, UpstreamOrderResult $result): void
    {
        DB::transaction(function () use ($order, $result): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === Order::STATUS_COMPLETED) {
                return;
            }

            // What Databundleshub actually charged our float = our real base cost for this order.
            if ($result->price !== null) {
                $locked->upstream_cost = $result->price;
            }
            $locked->upstream_status = $result->orderStatus;
            $locked->upstream_request_id ??= $result->requestId;
            $locked->upstream_reference ??= $result->transactionReference;
            $locked->status = Order::STATUS_COMPLETED;
            $locked->completed_at = now();
            $locked->last_polled_at = now();
            $locked->save();

            Earning::query()
                ->where('order_id', $locked->getKey())
                ->where('status', Earning::STATUS_PENDING)
                ->update([
                    'status' => Earning::STATUS_CREDITED,
                    'credited_at' => now(),
                ]);
        });
    }

    /**
     * Order failed/rejected (or unreachable): mark failed, reverse the pending earnings,
     * and refund the seller's wallet debit if this was a prepaid sale (§3 step 7). A
     * delivered order is never reversed here.
     */
    public function reverse(Order $order, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $reason): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [Order::STATUS_FAILED, Order::STATUS_COMPLETED], true)) {
                return;
            }

            $locked->status = Order::STATUS_FAILED;
            $locked->failed_at = now();
            $locked->last_polled_at = now();
            if ($reason !== null) {
                $locked->failure_reason = mb_substr($reason, 0, 255);
            }
            $locked->save();

            Earning::query()
                ->where('order_id', $locked->getKey())
                ->where('status', Earning::STATUS_PENDING)
                ->update(['status' => Earning::STATUS_REVERSED]);

            $this->refundSellerWallet($locked);
        });
    }

    /**
     * Admin refund of an order whose money already settled — Databundleshub's `refunded`
     * outcome for our use case (e.g. a delivered order the customer never received). Mirrors
     * their reversal engine: lock the row, re-check under the lock so it's exactly-once, mark
     * refunded, reverse the (pending or already-credited) earnings, and return the seller's
     * deposit. A failed order already gave the money back and is never refunded again.
     *
     * @return array{refunded: bool, amount: float, message: string}
     */
    public function refund(Order $order, ?string $reason = null): array
    {
        return DB::transaction(function () use ($order, $reason): array {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [Order::STATUS_FAILED, Order::STATUS_REFUNDED], true)) {
                return ['refunded' => false, 'amount' => 0.0, 'message' => 'Order money was already returned.'];
            }

            $locked->status = Order::STATUS_REFUNDED;
            $locked->refunded_at = now();
            if ($reason !== null) {
                $locked->failure_reason = mb_substr($reason, 0, 255);
            }
            $locked->save();

            Earning::query()
                ->where('order_id', $locked->getKey())
                ->whereIn('status', [Earning::STATUS_PENDING, Earning::STATUS_CREDITED])
                ->update(['status' => Earning::STATUS_REVERSED]);

            $this->refundSellerWallet($locked);

            $amount = $locked->channel === Order::CHANNEL_PREPAID ? (float) $locked->seller_cost : 0.0;

            return [
                'refunded' => true,
                'amount' => $amount,
                'message' => "Order {$locked->reference} refunded.",
            ];
        });
    }

    /**
     * Give back the deposit that was debited at dispatch. Online sales debited nothing.
     */
    private function refundSellerWallet(Order $order): void
    {
        if ($order->channel !== Order::CHANNEL_PREPAID) {
            return;
        }

        $seller = $order->seller;
        if ($seller === null || ! method_exists($seller, 'walletOrCreate')) {
            return;
        }

        $seller->walletOrCreate()->credit(
            (float) $order->seller_cost,
            self::TXN_REFUND,
            $order->reference,
            "Refund for reversed order {$order->reference}",
        );
    }
}
