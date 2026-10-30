<?php

namespace App\Console\Commands;

use App\Services\Payments\PendingPaymentSweeper;
use Illuminate\Console\Command;

/**
 * Re-checks every unconfirmed gateway payment (storefront orders + wallet top-ups) and expires the
 * ones still unconfirmed after config('payments.expiry_hours'). Runs every 5 minutes off schedule:run.
 */
class VerifyPendingPayments extends Command
{
    protected $signature = 'payments:verify-pending';

    protected $description = 'Verify pending gateway payments and expire stale ones.';

    public function handle(PendingPaymentSweeper $sweeper): int
    {
        $orders = $sweeper->sweepOrders();
        $topups = $sweeper->sweepTopups();

        $this->info("Orders: {$orders['resolved']} resolved, {$orders['expired']} expired.");
        $this->info("Top-ups: {$topups['resolved']} resolved, {$topups['expired']} expired.");

        return self::SUCCESS;
    }
}
