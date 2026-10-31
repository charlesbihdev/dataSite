<?php

namespace App\Services\Resellers;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Accuracy rules for a sale's profit split. `issues()` explains one order; `whereHasIssue()` is the
 * same rules in SQL so the "problems only" filter and the per-row check never disagree.
 *
 * Expected shares come straight from the frozen cascade (see ProfitSplit):
 *   subagent = customer_price − seller_cost, agent = seller_cost − agent_cost,
 *   platform = agent_cost − DBH cost (actual upstream_cost, else expected base_cost).
 * On an agent's own sale the first share is their shop profit and there is no commission.
 */
final class ProfitSplitAuditor
{
    private const TOLERANCE = 0.009;

    // Earnings only exist once a sale is dispatched; earlier statuses can't be "missing" one.
    private const DISPATCHED = [Order::STATUS_PROCESSING, Order::STATUS_COMPLETED];

    /**
     * @return array{subagent: float, agent: float, platform: ?float, dbh: ?float, dbhActual: bool}
     */
    public function shares(Order $order): array
    {
        $dbh = $order->upstream_cost ?? $order->base_cost;

        return [
            'subagent' => round((float) $order->customer_price - (float) $order->seller_cost, 2),
            'agent' => round((float) $order->seller_cost - (float) $order->agent_cost, 2),
            'platform' => $dbh === null ? null : round((float) $order->agent_cost - (float) $dbh, 2),
            'dbh' => $dbh === null ? null : (float) $dbh,
            'dbhActual' => $order->upstream_cost !== null,
        ];
    }

    /**
     * Human-readable problems with one order's split; empty means it checks out.
     * Expects `seller` and `earnings` loaded.
     *
     * @return list<string>
     */
    public function issues(Order $order): array
    {
        $shares = $this->shares($order);
        $issues = [];
        $ownSale = $order->seller_type === (new Agent)->getMorphClass();
        $labels = $ownSale
            ? ['subagent' => 'Shop profit', 'platform' => 'Platform']
            : ['subagent' => 'Subagent', 'agent' => 'Agent', 'platform' => 'Platform'];

        foreach ($labels as $key => $label) {
            if ($shares[$key] !== null && $shares[$key] < 0) {
                $issues[] = "{$label} share is negative (".number_format($shares[$key], 2).')';
            }
        }

        $expected = $ownSale
            ? [Earning::TYPE_SHOP_PROFIT => ['Shop profit', $shares['subagent'], (new Agent)->getMorphClass(), $order->seller_id]]
            : [
                Earning::TYPE_SHOP_PROFIT => ['Subagent', $shares['subagent'], (new Subagent)->getMorphClass(), $order->seller_id],
                Earning::TYPE_COMMISSION => ['Agent', $shares['agent'], (new Agent)->getMorphClass(), $order->seller?->agent_id],
            ];

        foreach ($expected as $type => [$label, $amount, $earnerType, $earnerId]) {
            $earning = $order->earnings->firstWhere('type', $type);

            if ($earning === null) {
                if ($amount > self::TOLERANCE && $order->payment_status === Order::PAYMENT_PAID && in_array($order->status, self::DISPATCHED, true)) {
                    $issues[] = "{$label} earning missing (expected ".number_format($amount, 2).')';
                }

                continue;
            }

            if (abs((float) $earning->amount - $amount) > self::TOLERANCE) {
                $issues[] = "Stored {$label} earning ".number_format((float) $earning->amount, 2).', expected '.number_format($amount, 2);
            }

            if ($earning->earner_type !== $earnerType || (int) $earning->earner_id !== (int) $earnerId) {
                $issues[] = "{$label} earning was credited to the wrong account";
            }
        }

        return $issues;
    }

    /** @param  Builder<Order>  $query */
    public function whereOwnSaleHasIssue(Builder $query): void
    {
        $agent = (new Agent)->getMorphClass();
        $tol = (string) self::TOLERANCE;

        $query->where(function (Builder $q) use ($agent, $tol): void {
            $q->whereColumn('orders.customer_price', '<', 'orders.seller_cost')
                ->orWhereRaw('orders.agent_cost < COALESCE(orders.upstream_cost, orders.base_cost)')
                ->orWhereHas('earnings', fn (Builder $e) => $e->whereRaw(
                    '(earnings.type = ? AND (ABS(earnings.amount - (orders.customer_price - orders.seller_cost)) > '.$tol
                    .' OR earnings.earner_type <> ? OR earnings.earner_id <> orders.seller_id))',
                    [Earning::TYPE_SHOP_PROFIT, $agent],
                ))
                ->orWhere(fn (Builder $m) => $m->where('orders.payment_status', Order::PAYMENT_PAID)
                    ->whereIn('orders.status', self::DISPATCHED)
                    ->whereRaw('orders.customer_price - orders.seller_cost > '.$tol)
                    ->whereDoesntHave('earnings', fn (Builder $e) => $e->where('type', Earning::TYPE_SHOP_PROFIT)));
        });
    }

    /** @param  Builder<Order>  $query */
    public function whereHasIssue(Builder $query): void
    {
        $subagent = (new Subagent)->getMorphClass();
        $agent = (new Agent)->getMorphClass();
        // Inlined, not bound: SQLite compares a bound string as TEXT, so `x > '0.009'` is never true.
        $tol = (string) self::TOLERANCE;

        $query->where(function (Builder $q) use ($subagent, $agent, $tol): void {
            $q->whereColumn('orders.customer_price', '<', 'orders.seller_cost')
                ->orWhereColumn('orders.seller_cost', '<', 'orders.agent_cost')
                ->orWhereRaw('orders.agent_cost < COALESCE(orders.upstream_cost, orders.base_cost)')
                ->orWhereHas('earnings', fn (Builder $e) => $e->whereRaw(
                    '((earnings.type = ? AND (ABS(earnings.amount - (orders.customer_price - orders.seller_cost)) > '.$tol
                    .' OR earnings.earner_type <> ? OR earnings.earner_id <> orders.seller_id))'
                    .' OR (earnings.type = ? AND (ABS(earnings.amount - (orders.seller_cost - orders.agent_cost)) > '.$tol
                    .' OR earnings.earner_type <> ? OR earnings.earner_id <> (SELECT agent_id FROM subagents WHERE subagents.id = orders.seller_id))))',
                    [Earning::TYPE_SHOP_PROFIT, $subagent, Earning::TYPE_COMMISSION, $agent],
                ))
                ->orWhere(fn (Builder $m) => $m->where('orders.payment_status', Order::PAYMENT_PAID)
                    ->whereIn('orders.status', self::DISPATCHED)
                    ->where(fn (Builder $w) => $w
                        ->where(fn (Builder $s) => $s->whereRaw('orders.customer_price - orders.seller_cost > '.$tol)
                            ->whereDoesntHave('earnings', fn (Builder $e) => $e->where('type', Earning::TYPE_SHOP_PROFIT)))
                        ->orWhere(fn (Builder $c) => $c->whereRaw('orders.seller_cost - orders.agent_cost > '.$tol)
                            ->whereDoesntHave('earnings', fn (Builder $e) => $e->where('type', Earning::TYPE_COMMISSION)))));
        });
    }
}
