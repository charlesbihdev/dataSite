<?php

namespace App\Services\Pricing;

use App\Models\BaseCost;

/**
 * The cost-floor guardrail (ARCHITECTURE §4): our selling rate may never sit below what we pay
 * Databundleshub. Fulfillment being upstream does not dictate price — this only sets the floor.
 */
class CostFloor
{
    /**
     * Highest cost_per_gb among active base-cost bands overlapping [minGb, maxGb] on a network.
     * A tier price spanning that range must clear the highest cost it touches. Null = no cost set.
     */
    public function forRange(string $network, float $minGb, float $maxGb): ?float
    {
        $cost = BaseCost::query()
            ->where('is_active', true)
            ->where('network', $network)
            ->where('min_gb', '<=', $maxGb)
            ->where('max_gb', '>=', $minGb)
            ->max('cost_per_gb');

        return $cost !== null ? (float) $cost : null;
    }

    /**
     * Cost band covering a single size — used when snapshotting base_cost onto an order.
     */
    public function forGb(string $network, float $gb): ?float
    {
        $band = BaseCost::query()->forBand($network, $gb)->first();

        return $band !== null ? (float) $band->cost_per_gb : null;
    }
}
