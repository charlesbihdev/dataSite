<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property bool $is_default
 * @property bool $is_undeletable
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Agent[] $agents
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\TierPrice[] $prices
 */
#[Fillable(['name', 'is_active'])]
class PricingTier extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_undeletable' => 'boolean',
        ];
    }

    /** @return HasMany<Agent, $this> */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /** @return HasMany<TierPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(TierPrice::class);
    }
}
