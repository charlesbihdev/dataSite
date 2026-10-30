<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\WalletTransaction;

/**
 * Reads a wallet order's money from the ledger itself rather than the payment_status flag: every
 * purchase debit and refund credit carries the order reference, so their sum is what's held.
 */
class OrderWalletLedger
{
    /** Amount currently taken from the seller's wallet for this order (0 when refunded or never debited). */
    public function held(Order $order): float
    {
        $seller = $order->seller;
        if ($seller === null || ! method_exists($seller, 'walletOrCreate')) {
            return 0.0;
        }
        $wallet = $seller->walletOrCreate();

        $net = (float) WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->where('reference', $order->reference)
            ->whereIn('type', [OrderDispatchService::TXN_PURCHASE, OrderSettlementService::TXN_REFUND])
            ->sum('amount');

        return max(0.0, round(-$net, 2));
    }
}
