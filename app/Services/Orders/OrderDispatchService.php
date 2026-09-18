<?php

namespace App\Services\Orders;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Earning;
use App\Models\Order;
use App\Services\Databundleshub\UpstreamClient;
use App\Services\Databundleshub\UpstreamException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Places a sale end to end (ARCHITECTURE §3): freeze the cascade, debit the seller's
 * deposit, record the pending profit splits, call Databundleshub, then settle now or
 * hand off to the poller. The wallet debit and the upstream call are deliberately
 * separated — money moves in a DB transaction; network I/O happens outside it.
 */
class OrderDispatchService
{
    public const TXN_PURCHASE = 'order_purchase';

    /**
     * User-facing note stored on a held order. Kept deliberately generic — failure_reason is
     * shown to sellers (Developer API) and admins, so the technical cause goes to the log, never
     * here.
     */
    public const HELD_REASON = 'Awaiting supplier connection — will be dispatched on retry.';

    public function __construct(
        private readonly UpstreamClient $client,
        private readonly OrderSettlementService $settlement,
        private readonly ProfitSplit $profitSplit,
    ) {}

    /**
     * @throws InsufficientBalanceException if a prepaid seller can't cover the cost
     */
    public function dispatch(NewOrderData $data): Order
    {
        $order = $this->createAndReserve($data);

        $this->sendUpstream($order);

        return $order->refresh();
    }

    /**
     * Create a storefront order that is waiting on the customer's gateway payment. Unlike a prepaid
     * dispatch, NO money moves and nothing is sent upstream yet: the order sits AWAITING / PENDING
     * with no wallet debit and no earnings, until the payment is confirmed and {@see fulfillPaid()}
     * takes over (decision #12). The cascade is already frozen by the caller (StorefrontCheckoutService).
     */
    public function createStorefrontAwaiting(NewOrderData $data): Order
    {
        /** @var Order $order */
        $order = $data->seller->orders()->create([
            'reference' => $this->uniqueReference(),
            'idempotency_key' => $data->idempotencyKey,
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_AWAITING,
            'network' => $data->network,
            'capacity_gb' => $data->capacityGb,
            'beneficiary_phone' => $data->beneficiaryPhone,
            'customer_email' => $data->customerEmail,
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => $data->customerPrice,
            'seller_cost' => $data->sellerCost,
            'agent_cost' => $data->agentCost,
            'base_cost' => $data->baseCost,
            'status' => Order::STATUS_PENDING,
        ]);

        return $order;
    }

    /**
     * Fulfill an order whose payment just cleared (the storefront/gateway path). The order
     * already exists — created AWAITING with no wallet debit — so now that an admin has verified
     * the money, record its earnings (if not already), move it to processing, and push upstream.
     * This is the same tail as a prepaid dispatch, guarded so a double-verify can't re-send.
     */
    public function fulfillPaid(Order $order): Order
    {
        DB::transaction(function () use ($order): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== Order::STATUS_PENDING || $locked->upstream_request_id !== null) {
                return; // already dispatched — don't record earnings or send twice.
            }

            if (Earning::query()->where('order_id', $locked->getKey())->doesntExist()) {
                foreach ($this->profitSplit->for($locked) as $share) {
                    $share['earner']->earnings()->create([
                        'order_id' => $locked->getKey(),
                        'type' => $share['type'],
                        'amount' => $share['amount'],
                        'status' => Earning::STATUS_PENDING,
                    ]);
                }
            }

            // Earnings recorded; the order stays PENDING until the supplier accepts it in
            // sendUpstream (or is held there if the supplier is unreachable).
        });

        $fresh = $order->refresh();

        if ($fresh->status === Order::STATUS_PENDING && $fresh->upstream_request_id === null) {
            $this->sendUpstream($fresh);
        }

        return $fresh->refresh();
    }

    /**
     * Freeze the order, debit the deposit, and record pending earnings — atomically.
     * If the debit fails (insufficient funds) the whole thing rolls back: no order,
     * no earnings, no upstream call.
     */
    private function createAndReserve(NewOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            /** @var Order $order */
            $order = $data->seller->orders()->create([
                'reference' => $this->uniqueReference(),
                'idempotency_key' => $data->idempotencyKey,
                'source' => $data->source,
                // This path secures the money in-transaction (wallet debit below), so it's paid
                // outright. The storefront/gateway flow creates its orders as AWAITING elsewhere.
                'payment_status' => Order::PAYMENT_PAID,
                'network' => $data->network,
                'capacity_gb' => $data->capacityGb,
                'beneficiary_phone' => $data->beneficiaryPhone,
                'channel' => $data->channel,
                'customer_price' => $data->customerPrice,
                'seller_cost' => $data->sellerCost,
                'agent_cost' => $data->agentCost,
                'base_cost' => $data->baseCost,
                'status' => Order::STATUS_PENDING,
            ]);

            if ($data->debitsWallet()) {
                $data->seller->walletOrCreate()->debit(
                    $data->sellerCost,
                    self::TXN_PURCHASE,
                    $order->reference,
                    "Bundle purchase {$order->reference}",
                );
            }

            foreach ($this->profitSplit->for($order) as $share) {
                $share['earner']->earnings()->create([
                    'order_id' => $order->getKey(),
                    'type' => $share['type'],
                    'amount' => $share['amount'],
                    'status' => Earning::STATUS_PENDING,
                ]);
            }

            // Stays PENDING (held): the money is reserved above, but the order is only promoted
            // to PROCESSING once the supplier actually accepts it (in sendUpstream). If the
            // supplier is unreachable it safely remains a held PENDING order.
            return $order;
        });
    }

    /**
     * Retry a failed order. Payment invariant: PAID ⇒ money is still held (storefront gateway, or an
     * order we never refunded) → just re-send. NOT paid ⇒ the reversal refunded it → re-debit the
     * seller (prepaid) to secure it again, then send. Fresh pending earnings replace the reversed
     * ones from the failed attempt. Throws InsufficientBalanceException if a re-debit can't cover it.
     */
    public function redispatch(Order $order): Order
    {
        DB::transaction(function () use ($order): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [Order::STATUS_COMPLETED, Order::STATUS_PROCESSING], true)) {
                return; // already done or in flight — nothing to retry
            }

            if ($locked->payment_status !== Order::PAYMENT_PAID) {
                if ($locked->channel === Order::CHANNEL_PREPAID) {
                    $locked->seller->walletOrCreate()->debit(
                        $locked->seller_cost,
                        self::TXN_PURCHASE,
                        $locked->reference,
                        "Re-dispatch {$locked->reference}",
                    );
                }
                $locked->payment_status = Order::PAYMENT_PAID;
            }

            Earning::query()->where('order_id', $locked->getKey())->delete();
            foreach ($this->profitSplit->for($locked) as $share) {
                $share['earner']->earnings()->create([
                    'order_id' => $locked->getKey(),
                    'type' => $share['type'],
                    'amount' => $share['amount'],
                    'status' => Earning::STATUS_PENDING,
                ]);
            }

            // Reset to PENDING; sendUpstream promotes to PROCESSING only once the supplier accepts,
            // or holds it PENDING again if the supplier is still unreachable.
            $locked->status = Order::STATUS_PENDING;
            $locked->failure_reason = null;
            $locked->upstream_request_id = null;
            $locked->upstream_reference = null;
            $locked->save();
        });

        $fresh = $order->refresh();

        if ($fresh->status === Order::STATUS_PENDING && $fresh->upstream_request_id === null) {
            $this->sendUpstream($fresh);
        }

        return $fresh->refresh();
    }

    /**
     * Call Databundleshub, then route on the outcome. Note the two very different failures:
     *   - We never reached the supplier (unconfigured / unreachable / connection) → HOLD the order
     *     as PENDING. Money stays reserved, earnings stay pending, nothing is reversed — this is our
     *     side failing, not a rejected order. An admin retries once the connection is restored.
     *   - The supplier accepted then rejected it (bad number, out of stock) → reverse (real failure).
     * Delivered immediately → settle; accepted and still working → PROCESSING + poll.
     */
    private function sendUpstream(Order $order): void
    {
        try {
            $result = $this->client->placeOrder($order->reference, $order->beneficiary_phone, (int) $order->capacity_gb);
        } catch (UpstreamException $e) {
            // The technical cause (no config / cURL error / DNS) is for ops only — log it, and store
            // a generic seller-safe note since failure_reason surfaces on the Developer API.
            Log::warning('Order held — supplier unavailable', [
                'order' => $order->reference,
                'reason' => $e->getMessage(),
            ]);

            $order->forceFill([
                'status' => Order::STATUS_PENDING,
                'failure_reason' => self::HELD_REASON,
            ])->save();

            return;
        }

        $order->forceFill([
            'upstream_request_id' => $result->requestId,
            'upstream_reference' => $result->transactionReference,
            'upstream_status' => $result->orderStatus,
        ])->save();

        if ($result->isFailed()) {
            $this->settlement->reverse($order, $result->errorMessage ?? 'Upstream rejected the order.');

            return;
        }

        if ($result->isCompleted()) {
            $this->settlement->settle($order, $result);

            return;
        }

        // Accepted by the supplier and still working — now it is genuinely PROCESSING.
        $order->forceFill(['status' => Order::STATUS_PROCESSING])->save();
        PollUpstreamOrderStatus::dispatch($order->getKey());
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'DS-'.strtoupper(Str::random(10));
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
