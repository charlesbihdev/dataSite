<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subagent's own selling price for a data package (network + capacity). Cost is the price their
 * agent set for sub-agents (AgentPackagePrice::subagent_price) frozen at save time. Subagents are the
 * bottom rung, so there is no onward sub-agent price.
 *
 * @property int $id
 * @property int $subagent_id
 * @property string $network
 * @property int $capacity_gb
 * @property string $cost_price
 * @property string $selling_price
 * @property bool $is_active
 */
#[Fillable(['subagent_id', 'network', 'capacity_gb', 'cost_price', 'selling_price', 'is_active'])]
class SubagentPackagePrice extends Model
{
    protected function casts(): array
    {
        return [
            'capacity_gb' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Subagent, $this> */
    public function subagent(): BelongsTo
    {
        return $this->belongsTo(Subagent::class);
    }
}
