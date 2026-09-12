<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $network
 * @property string $min_gb
 * @property string $max_gb
 * @property string $cost_per_gb
 * @property bool $is_active
 */
#[Fillable(['network', 'min_gb', 'max_gb', 'cost_per_gb', 'is_active'])]
class BaseCost extends Model
{
    protected function casts(): array
    {
        return [
            'min_gb' => 'decimal:2',
            'max_gb' => 'decimal:2',
            'cost_per_gb' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active cost band covering a given size on a network (min_gb ≤ gb ≤ max_gb).
     *
     * @param  Builder<BaseCost>  $query
     * @return Builder<BaseCost>
     */
    public function scopeForBand(Builder $query, string $network, float $gb): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('network', $network)
            ->where('min_gb', '<=', $gb)
            ->where('max_gb', '>=', $gb)
            ->orderBy('min_gb');
    }
}
