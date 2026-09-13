<?php

namespace App\Services\Storefront;

use App\Models\Agent;
use App\Models\AgentPackagePrice;
use App\Models\Order;
use App\Services\Orders\NewOrderData;
use App\Services\Orders\OrderDispatchService;
use App\Services\Pricing\PriceQuote;
use App\Support\GhanaMobileNetwork;

/**
 * Turns a public storefront checkout into an AWAITING order. A customer buys a bundle from an agent's
 * retail store at the agent's own selling price (the active AgentPackagePrice), pays through a gateway,
 * and the order stays awaiting until that payment is verified (then {@see OrderDispatchService::fulfillPaid}
 * dispatches it). The frozen cascade for a direct agent sale is:
 *   customer_price = selling_price (retail)   seller_cost = agent_cost = cost_price (tier)   base_cost = platform.
 */
class StorefrontCheckoutService
{
    public function __construct(
        private readonly OrderDispatchService $dispatch,
        private readonly PriceQuote $quote,
    ) {}

    /**
     * @throws CheckoutException when the number/package is invalid or the store doesn't sell it
     */
    public function checkout(Agent $agent, string $phone, string $network, int $capacityGb): Order
    {
        $normalized = GhanaMobileNetwork::normalize($phone);
        if ($normalized === '') {
            throw new CheckoutException('Enter a valid Ghana mobile number.');
        }

        $detected = GhanaMobileNetwork::detect($normalized);
        if ($detected === null || $detected !== $network) {
            throw new CheckoutException('That number does not match the selected network.');
        }

        $package = $this->activePackage($agent, $network, $capacityGb);
        if ($package === null) {
            throw new CheckoutException('This package is not available on this store.');
        }

        $baseCost = $this->quote->for($agent, $network, $capacityGb)['baseCost'] ?? 0.0;
        $cost = (float) $package->cost_price;

        $order = $this->dispatch->createStorefrontAwaiting(new NewOrderData(
            seller: $agent,
            network: $network,
            capacityGb: $capacityGb,
            beneficiaryPhone: $normalized,
            customerPrice: (float) $package->selling_price,
            sellerCost: $cost,
            agentCost: $cost,
            baseCost: $baseCost,
            channel: Order::CHANNEL_ONLINE,
            source: Order::SOURCE_STOREFRONT,
        ));

        return $order;
    }

    private function activePackage(Agent $agent, string $network, int $capacityGb): ?AgentPackagePrice
    {
        return $agent->packagePrices()
            ->where('is_active', true)
            ->where('network', $network)
            ->where('capacity_gb', $capacityGb)
            ->first();
    }
}
