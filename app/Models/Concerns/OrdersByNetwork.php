<?php

namespace App\Models\Concerns;

use App\Support\GhanaMobileNetwork;
use Illuminate\Database\Eloquent\Builder;

/** Orders package rows by the canonical network order (MTN, Telecel, AirtelTigo), then capacity. */
trait OrdersByNetwork
{
    public function scopeOrderByNetwork(Builder $query): Builder
    {
        $order = GhanaMobileNetwork::order();
        $cases = implode(' ', array_map(fn (int $i): string => 'when ? then '.$i, array_keys($order)));

        return $query
            ->orderByRaw("case network {$cases} else ".count($order).' end', $order)
            ->orderBy('capacity_gb');
    }
}
