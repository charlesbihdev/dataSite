<?php

namespace App\Services\Orders;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;

/**
 * The single place profit-sharing is decided (decision #8). Given an order's frozen
 * cascade, it returns who earns what — each cut is the difference of two adjacent levels.
 *
 * Subagent sale (three-way):
 *   subagent shop_profit = customer_price − seller_cost   (their retail markup)
 *   agent    commission  = seller_cost   − agent_cost     (the parent agent's cut)
 *   platform profit      = agent_cost    − base_cost      (implicit; no superadmin earner)
 *
 * Agent direct sale (two-way):
 *   agent    shop_profit = customer_price − seller_cost
 *   platform profit      = seller_cost    − base_cost     (implicit)
 *
 * Platform profit is derivable from the frozen cascade at any time, so it is not stored
 * as an Earning (the earnings pool only has agent/subagent earners).
 *
 * @phpstan-type EarningShare array{earner: Agent|Subagent, type: string, amount: float}
 */
final class ProfitSplit
{
    /**
     * @return list<EarningShare>
     */
    public function for(Order $order): array
    {
        $seller = $order->seller;
        $customerPrice = (float) $order->customer_price;
        $sellerCost = (float) $order->seller_cost;
        $agentCost = (float) $order->agent_cost;

        $shares = [];

        if ($seller instanceof Subagent) {
            $shares[] = [
                'earner' => $seller,
                'type' => Earning::TYPE_SHOP_PROFIT,
                'amount' => round($customerPrice - $sellerCost, 2),
            ];

            $agent = $seller->agent;
            if ($agent instanceof Agent) {
                $shares[] = [
                    'earner' => $agent,
                    'type' => Earning::TYPE_COMMISSION,
                    'amount' => round($sellerCost - $agentCost, 2),
                ];
            }
        } elseif ($seller instanceof Agent) {
            $shares[] = [
                'earner' => $seller,
                'type' => Earning::TYPE_SHOP_PROFIT,
                'amount' => round($customerPrice - $sellerCost, 2),
            ];
        }

        // Drop non-positive cuts so a zero-markup sale doesn't create noise rows.
        return array_values(array_filter($shares, fn (array $share): bool => $share['amount'] > 0));
    }
}
