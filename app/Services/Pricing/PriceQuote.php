<?php

namespace App\Services\Pricing;

use App\Models\Agent;
use App\Models\BaseCost;
use App\Models\Subagent;
use App\Models\TierPrice;

/**
 * Resolves the price a seller pays for a bundle from its pricing tier — the piece the API needs,
 * since a caller sends only phone + network + capacity and never a price. A subagent inherits its
 * parent agent's tier (we have no separate subagent rate card). Mirrors how Databundleshub's
 * PricingResolver turns (network, role, capacity) into an amount.
 */
class PriceQuote
{
    /**
     * @return array{pricePerGb: float, amount: float, baseCost: float}|null null when unpriceable
     */
    public function for(Agent|Subagent $seller, string $network, int $capacityGb): ?array
    {
        $tierId = $seller instanceof Agent ? $seller->pricing_tier_id : $seller->agent?->pricing_tier_id;
        if ($tierId === null) {
            return null;
        }

        $pricePerGb = TierPrice::query()
            ->where('is_active', true)
            ->where('pricing_tier_id', $tierId)
            ->where('network', $network)
            ->where('min_gb', '<=', $capacityGb)
            ->where('max_gb', '>=', $capacityGb)
            ->orderBy('min_gb')
            ->value('price_per_gb');

        if ($pricePerGb === null) {
            $pricePerGb = TierPrice::query()
                ->where('is_active', true)
                ->where('pricing_tier_id', $tierId)
                ->where('network', 'default')
                ->where('min_gb', '<=', $capacityGb)
                ->where('max_gb', '>=', $capacityGb)
                ->orderBy('min_gb')
                ->value('price_per_gb');
        }

        if ($pricePerGb === null) {
            return null;
        }

        $baseCost = BaseCost::query()->forBand($network, $capacityGb)->value('cost_per_gb');

        if ($baseCost === null) {
            $baseCost = BaseCost::query()->forBand('default', $capacityGb)->value('cost_per_gb');
        }

        return [
            'pricePerGb' => (float) $pricePerGb,
            'amount' => round((float) $pricePerGb * $capacityGb, 2),
            'baseCost' => round((float) ($baseCost ?? 0) * $capacityGb, 2),
        ];
    }
}
