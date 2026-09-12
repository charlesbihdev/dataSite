<?php

namespace App\Services\Orders;

use App\Models\Agent;
use App\Models\Order;
use App\Models\Subagent;

/**
 * The sale inputs handed to OrderDispatchService, with the full price cascade already
 * resolved by the caller. These four money figures are frozen onto the order so
 * profit-sharing is pure subtraction, immune to later price edits (ARCHITECTURE §3, decision #6).
 *
 * Cascade (each ≥ the next): customer_price ≥ seller_cost ≥ agent_cost ≥ base_cost.
 */
final class NewOrderData
{
    public function __construct(
        public readonly Agent|Subagent $seller,
        public readonly string $network,
        public readonly int $capacityGb,
        public readonly string $beneficiaryPhone,
        public readonly float $customerPrice,
        public readonly float $sellerCost,
        public readonly float $agentCost,
        public readonly float $baseCost,
        public readonly string $channel = Order::CHANNEL_PREPAID,
        public readonly string $source = 'portal',
        public readonly ?string $idempotencyKey = null,
    ) {}

    /**
     * Prepaid sales spend the seller's deposit wallet; online sales are paid by the
     * customer through a gateway, so no wallet is debited (and nothing to refund).
     */
    public function debitsWallet(): bool
    {
        return $this->channel === Order::CHANNEL_PREPAID;
    }
}
