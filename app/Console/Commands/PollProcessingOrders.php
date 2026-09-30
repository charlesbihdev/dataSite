<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Orders\OrderPoller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety-net poller: sweep every order still in `processing` (with an upstream reference) and pull its
 * Databundleshub status. Runs every minute off schedule:run — mirrors DBH's own cron sync model, so
 * polling never depends on the instant job surviving and never gives up after a fixed number of tries.
 */
class PollProcessingOrders extends Command
{
    protected $signature = 'orders:poll-processing';

    protected $description = 'Pull upstream status for all processing orders (safety-net sweep).';

    public function handle(OrderPoller $poller): int
    {
        $polled = 0;

        Order::query()
            ->where('status', Order::STATUS_PROCESSING)
            ->whereNotNull('upstream_request_id')
            ->where('created_at', '>=', now()->subDays(14)) // don't chase indefinitely-stuck stragglers
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($poller, &$polled): void {
                foreach ($orders as $order) {
                    try {
                        if ($poller->poll($order)) {
                            $polled++;
                        }
                    } catch (\Throwable $e) {
                        // One order's transport failure must not abort the sweep — the next run retries it.
                        Log::warning('Poll sweep: order check failed', ['order' => $order->reference, 'error' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Polled {$polled} processing order(s).");

        return self::SUCCESS;
    }
}
