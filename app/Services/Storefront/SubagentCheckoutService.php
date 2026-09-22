<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Models\Subagent;
use App\Models\SubagentPackagePrice;
use App\Services\Orders\NewOrderData;
use App\Services\Orders\OrderDispatchService;
use App\Services\Pricing\PriceQuote;
use App\Support\GhanaMobileNetwork;

/**
 * Turns a public D3 subagent-storefront checkout into an AWAITING order. A customer buys one of the
 * subagent's active packages at the subagent's selling price, pays through a gateway, and the order
 * stays awaiting until the payment is verified (then {@see OrderDispatchService::fulfillPaid} dispatches).
 *
 * The frozen cascade drives the three-way split (see ProfitSplit):
 *   customer_price = subagent selling_price
 *   seller_cost    = subagent cost_price   (= the agent's sub-agent price) → subagent margin
 *   agent_cost     = the agent's own cost  (their tier rate)               → agent commission
 *   base_cost      = platform floor                                         → platform profit
 */
class SubagentCheckoutService
{
    public function __construct(
        private readonly OrderDispatchService $dispatch,
        private readonly PriceQuote $quote,
    ) {}

    /**
     * @throws CheckoutException when the number/package is invalid or the store doesn't sell it
     */
    public function checkout(Subagent $subagent, string $phone, string $network, int $capacityGb): Order
    {
        $normalized = GhanaMobileNetwork::normalize($phone);
        if ($normalized === '') {
            throw new CheckoutException('Enter a valid Ghana mobile number.');
        }

        $detected = GhanaMobileNetwork::detect($normalized);
        if ($detected === null || $detected !== $network) {
            throw new CheckoutException('That number does not match the selected network.');
        }

        $package = $this->activePackage($subagent, $network, $capacityGb);
        if ($package === null) {
            throw new CheckoutException('This package is not available on this store.');
        }

        $agent = $subagent->agent;

        // The agent's own cost (their tier rate) for this package, and the platform floor. The agent's
        // commission = seller_cost − agent_cost, so we anchor agent_cost to their real cost.
        $agentPackage = $agent?->packagePrices()
            ->where('network', $network)
            ->where('capacity_gb', $capacityGb)
            ->first();

        $agentCost = $agentPackage !== null ? (float) $agentPackage->cost_price : (float) $package->cost_price;
        $baseCost = $agent !== null ? (float) ($this->quote->for($agent, $network, $capacityGb)['baseCost'] ?? 0.0) : 0.0;

        return $this->dispatch->createStorefrontAwaiting(new NewOrderData(
            seller: $subagent,
            network: $network,
            capacityGb: $capacityGb,
            beneficiaryPhone: $normalized,
            customerPrice: (float) $package->selling_price,
            sellerCost: (float) $package->cost_price,
            agentCost: $agentCost,
            baseCost: $baseCost,
            channel: Order::CHANNEL_ONLINE,
            source: Order::SOURCE_STOREFRONT,
        ));
    }

    private function activePackage(Subagent $subagent, string $network, int $capacityGb): ?SubagentPackagePrice
    {
        return $subagent->packagePrices()
            ->where('is_active', true)
            ->where('network', $network)
            ->where('capacity_gb', $capacityGb)
            ->first();
    }
}
