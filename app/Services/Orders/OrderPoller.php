<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Databundleshub\UpstreamClient;
use Illuminate\Support\Facades\Log;

/**
 * Checks one order's upstream (Databundleshub) status and acts on a terminal result. Shared by the
 * instant post-dispatch job and the every-minute sweep command (mirrors DBH's own cron sync model).
 */
class OrderPoller
{
    public function __construct(
        private readonly UpstreamClient $client,
        private readonly OrderSettlementService $settlement,
    ) {}

    /**
     * Poll one order. Returns false when there's nothing to poll (already terminal, or never sent
     * upstream). May throw UpstreamException on a transport/bot-block failure — the caller decides
     * whether to swallow it (the next sweep will retry).
     */
    public function poll(Order $order): bool
    {
        if (in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_FAILED], true) || $order->upstream_request_id === null) {
            return false;
        }

        $result = $this->client->orderStatus($order->upstream_request_id, $order->network, $order->id);

        Log::info('Upstream poll succeeded', [
            'order' => $order->reference,
            'success' => $result->success,
            'orderStatus' => $result->orderStatus,
            'outcome' => $result->isCompleted() ? 'delivered' : ($result->isFailed() ? 'failed' : 'still processing'),
        ]);

        if ($result->isCompleted()) {
            $this->settlement->settle($order, $result);

            return true;
        }

        if ($result->isFailed()) {
            $this->settlement->reverse($order, $result->errorMessage ?? 'Upstream reported failure.');

            return true;
        }

        $order->forceFill([
            'upstream_status' => $result->orderStatus,
            'last_polled_at' => now(),
        ])->save();

        return true;
    }
}
