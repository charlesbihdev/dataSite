<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Orders\OrderPoller;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * An INSTANT first status check right after an order is accepted, so a fast delivery settles within
 * seconds. Ongoing polling is the scheduled `orders:poll-processing` sweep (mirrors DBH's cron sync),
 * so this is one-shot: a transient failure here is swallowed because the sweep will re-check the order.
 */
class PollUpstreamOrderStatus implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(OrderPoller $poller): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }

        try {
            $poller->poll($order);
        } catch (\Throwable $e) {
            Log::warning('Instant poll failed; sweep will retry', ['order' => $order->reference, 'error' => $e->getMessage()]);
        }
    }
}
