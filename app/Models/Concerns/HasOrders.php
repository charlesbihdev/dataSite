<?php

namespace App\Models\Concerns;

use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives an account type (agent, subagent) the orders it placed as the seller.
 */
trait HasOrders
{
    /** @return MorphMany<Order, $this> */
    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'seller');
    }
}
