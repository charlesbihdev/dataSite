<?php

namespace App\Services\Orders;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Earning;
use App\Models\Order;
use App\Services\Databundleshub\UpstreamClient;
use App\Services\Databundleshub\UpstreamException;
use Illuminate\Support\Facades\DB;
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

        $this->sendUpstream($order, $data);

        return $order->refresh();
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

            $order->status = Order::STATUS_PROCESSING;
            $order->save();

            return $order;
        });
    }

    /**
     * Call Databundleshub, then route on the outcome: transport failure or business
     * rejection → reverse; delivered immediately → settle; still working → poll.
     */
    private function sendUpstream(Order $order, NewOrderData $data): void
    {
        try {
            $result = $this->client->placeOrder($order->reference, $data->beneficiaryPhone, $data->capacityGb);
        } catch (UpstreamException $e) {
            $this->settlement->reverse($order, 'Upstream unreachable: '.$e->getMessage());

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
