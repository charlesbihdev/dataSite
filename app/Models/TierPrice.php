<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pricing_tier_id
 * @property string $network
 * @property string $min_gb
 * @property string $max_gb
 * @property string $price_per_gb
 * @property bool $is_active
 */
#[Fillable(['pricing_tier_id', 'network', 'min_gb', 'max_gb', 'price_per_gb', 'is_active'])]
class TierPrice extends Model
{
    protected function casts(): array
    {
        return [
            'min_gb' => 'decimal:2',
            'max_gb' => 'decimal:2',
            'price_per_gb' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<PricingTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(PricingTier::class, 'pricing_tier_id');
    }
}
