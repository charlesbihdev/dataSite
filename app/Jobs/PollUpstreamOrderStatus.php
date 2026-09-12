<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Databundleshub\UpstreamClient;
use App\Services\Orders\OrderSettlementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Databundleshub does not push callbacks — status is poll-only (ARCHITECTURE §2). This job
 * checks one order and, when it reaches a terminal state, hands off to settlement. While it
 * is still processing the job re-queues itself with a delay until a terminal state or the
 * attempt cap is hit; after that the order stays `processing` for an admin to retry.
 */
class PollUpstreamOrderStatus implements ShouldQueue
{
    use Queueable;

    private const REPOLL_DELAY_SECONDS = 60;

    public int $tries = 12;

    public function __construct(public int $orderId) {}

    /**
     * Retry backoff for transport failures (UpstreamException bubbling out of handle()).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(UpstreamClient $client, OrderSettlementService $settlement): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }
        if (in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_FAILED], true)) {
            return;
        }
        if ($order->upstream_request_id === null) {
            return;
        }

        // A transport failure throws UpstreamException and lets the queue retry with backoff.
        $result = $client->orderStatus($order->upstream_request_id);

        if ($result->isCompleted()) {
            $settlement->settle($order, $result);

            return;
        }

        if ($result->isFailed()) {
            $settlement->reverse($order, $result->errorMessage ?? 'Upstream reported failure.');

            return;
        }

        $order->forceFill([
            'upstream_status' => $result->orderStatus,
            'last_polled_at' => now(),
        ])->save();

        // Still working upstream — put the same job back with a delay. Re-queuing this way
        // (not a fresh dispatch) keeps the attempt counter, so $tries caps the polling.
        $this->release(self::REPOLL_DELAY_SECONDS);
    }
}
