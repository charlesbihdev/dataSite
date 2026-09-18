<?php

namespace App\Services\Pricing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared guard for the pricing band tables (tier_prices, base_costs): two active bands on the
 * same network (and tier, for tier_prices) must never cover the same GB. Boundaries are inclusive
 * on both ends, so touching endpoints (…–10 and 10–…) count as an overlap — every size resolves
 * to exactly one band, keeping the money path deterministic.
 */
class BandOverlap
{
    /**
     * The first active band whose [min_gb, max_gb] intersects [minGb, maxGb], or null when the
     * range is free. Pass a query already scoped to the target table + network (+ tier).
     *
     * @param  Builder<covariant Model>  $within
     */
    public function conflicting(Builder $within, float $minGb, float $maxGb, ?int $ignoreId = null): ?Model
    {
        return $within
            ->where('is_active', true)
            ->where('min_gb', '<=', $maxGb)
            ->where('max_gb', '>=', $minGb)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->orderBy('min_gb')
            ->first();
    }

    /**
     * A band's range as a human label without trailing zeros, e.g. "1–10" or "2.5–5".
     */
    public function describe(Model $band): string
    {
        return $this->bound($band->min_gb).'–'.$this->bound($band->max_gb);
    }

    private function bound(string $value): string
    {
        $number = (float) $value;

        return $number == (int) $number ? (string) (int) $number : (string) $number;
    }
}
