<?php

namespace App\Models;

use App\Models\Concerns\OrdersByNetwork;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's own selling price for a data package (network + capacity). Cost is the agent's tier
 * rate frozen at save time; selling/sub-agent prices are what the agent charges. This is a
 * management record — it does not yet drive live checkout pricing (that stays on the tier cascade).
 *
 * @property int $id
 * @property int $agent_id
 * @property string $network
 * @property int $capacity_gb
 * @property string $cost_price
 * @property string $selling_price
 * @property string|null $subagent_price
 * @property bool $is_active
 */
#[Fillable(['agent_id', 'network', 'capacity_gb', 'cost_price', 'selling_price', 'subagent_price', 'is_active'])]
class AgentPackagePrice extends Model
{
    use OrdersByNetwork;

    protected function casts(): array
    {
        return [
            'capacity_gb' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'subagent_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
