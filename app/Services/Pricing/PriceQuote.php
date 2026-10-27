<?php

namespace App\Services\Pricing;

use App\Models\Agent;
use App\Models\AgentPackagePrice;
use App\Models\BaseCost;
use App\Models\Subagent;
use App\Models\TierPrice;

/**
 * Resolves the price a seller pays for a bundle — the piece the API and Place-Order cart need,
 * since a caller sends only phone + network + capacity and never a price. An agent pays its pricing
 * tier's rate; a subagent pays the sub-agent price its agent set on that exact package (the same cost
 * its storefront margin is built on), so the agent earns commission on every subagent purchase.
 * Mirrors how Databundleshub's PricingResolver turns (network, role, capacity) into an amount.
 */
class PriceQuote
{
    /**
     * @return array{pricePerGb: float, amount: float, baseCost: float}|null null when unpriceable
     */
    public function for(Agent|Subagent $seller, string $network, int $capacityGb): ?array
    {
        $pricePerGb = $seller instanceof Agent
            ? $this->tierPricePerGb($seller, $network, $capacityGb)
            : $this->subagentPricePerGb($seller, $network, $capacityGb);

        if ($pricePerGb === null) {
            return null;
        }

        $baseCost = BaseCost::query()->forBand($network, $capacityGb)->value('cost_per_gb');

        if ($baseCost === null) {
            $baseCost = BaseCost::query()->forBand('default', $capacityGb)->value('cost_per_gb');
        }

        return [
            'pricePerGb' => $pricePerGb,
            'amount' => round($pricePerGb * $capacityGb, 2),
            'baseCost' => round((float) ($baseCost ?? 0) * $capacityGb, 2),
        ];
    }

    private function tierPricePerGb(Agent $agent, string $network, int $capacityGb): ?float
    {
        if ($agent->pricing_tier_id === null) {
            return null;
        }

        $pricePerGb = $this->tierRate($agent->pricing_tier_id, $network, $capacityGb)
            ?? $this->tierRate($agent->pricing_tier_id, 'default', $capacityGb);

        return $pricePerGb !== null ? (float) $pricePerGb : null;
    }

    private function tierRate(int $tierId, string $network, int $capacityGb): mixed
    {
        return TierPrice::query()
            ->where('is_active', true)
            ->where('pricing_tier_id', $tierId)
            ->where('network', $network)
            ->where('min_gb', '<=', $capacityGb)
            ->where('max_gb', '>=', $capacityGb)
            ->orderBy('min_gb')
            ->value('price_per_gb');
    }

    private function subagentPricePerGb(Subagent $subagent, string $network, int $capacityGb): ?float
    {
        $price = AgentPackagePrice::query()
            ->where('agent_id', $subagent->agent_id)
            ->where('network', $network)
            ->where('capacity_gb', $capacityGb)
            ->where('is_active', true)
            ->whereNotNull('subagent_price')
            ->value('subagent_price');

        return $price !== null ? (float) $price / $capacityGb : null;
    }
}
