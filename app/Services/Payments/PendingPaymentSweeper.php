<?php

namespace App\Services\Payments;

use App\Models\BalanceTopup;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for gateway payments nobody confirmed (payer closed the tab, webhook lost, checkout
 * abandoned): re-checks every pending storefront order and wallet top-up through the same
 * confirmers the callback/webhooks use. Anything still unconfirmed past the expiry window gets
 * that one final check and is then marked expired — still verifiable by an admin afterwards.
 */
class PendingPaymentSweeper
{
    // Skip payments this young so we don't query a checkout the payer is still on.
    private const GRACE_MINUTES = 2;

    public function __construct(
        private readonly OrderPaymentConfirmer $orders,
        private readonly WalletTopupConfirmer $topups,
    ) {}

    /**
     * @return array{resolved: int, expired: int}
     */
    public function sweepOrders(): array
    {
        $tally = ['resolved' => 0, 'expired' => 0];
        $cutoff = $this->expiryCutoff();

        Order::query()
            ->where('channel', Order::CHANNEL_ONLINE)
            ->where('payment_status', Order::PAYMENT_AWAITING)
            ->where('created_at', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->chunkById(100, function ($orders) use ($cutoff, &$tally): void {
                foreach ($orders as $order) {
                    try {
                        if ($this->orders->confirm($order) !== PaymentVerifier::PENDING) {
                            $tally['resolved']++;
                        } elseif ($order->created_at <= $cutoff && $this->orders->expire($order)) {
                            $tally['expired']++;
                        }
                    } catch (\Throwable $e) {
                        // One order's gateway failure must not abort the sweep — the next run retries it.
                        Log::warning('Payment sweep: order check failed', ['order' => $order->reference, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $tally;
    }

    /**
     * @return array{resolved: int, expired: int}
     */
    public function sweepTopups(): array
    {
        $tally = ['resolved' => 0, 'expired' => 0];
        $cutoff = $this->expiryCutoff();

        BalanceTopup::query()
            ->where('status', BalanceTopup::STATUS_PENDING)
            ->where('created_at', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->chunkById(100, function ($topups) use ($cutoff, &$tally): void {
                foreach ($topups as $topup) {
                    try {
                        $this->topups->reverify($topup);

                        if ($topup->refresh()->status !== BalanceTopup::STATUS_PENDING) {
                            $tally['resolved']++;
                        } elseif ($topup->created_at <= $cutoff && $this->topups->expire($topup)) {
                            $tally['expired']++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Payment sweep: top-up check failed', ['topup' => $topup->reference, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $tally;
    }

    private function expiryCutoff(): CarbonInterface
    {
        return now()->subHours(max(1, (int) config('payments.expiry_hours', 24)));
    }
}
