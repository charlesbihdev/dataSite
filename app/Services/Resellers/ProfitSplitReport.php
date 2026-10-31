<?php

namespace App\Services\Resellers;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The admin Profit Splits list: every sale that reached settlement (paid, or already holding
 * earnings rows — a refund flips paid back to awaiting but keeps the reversed earnings), with each
 * party's share and the auditor's verdict. Two scopes: subagents' sales, or agents' own sales.
 */
final class ProfitSplitReport
{
    public const SETTLEMENTS = ['all', Earning::STATUS_CREDITED, Earning::STATUS_PENDING, Earning::STATUS_REVERSED];

    public const SCOPE_SUBAGENTS = 'subagents';

    public const SCOPE_OWN = 'own';

    public function __construct(private readonly ProfitSplitAuditor $auditor) {}

    /**
     * @return array{scope: string, agent: string, subagent: string, settlement: string, q: string, problems: bool, profit_only: bool, range: string, from: ?string, to: ?string}
     */
    public function filters(Request $request): array
    {
        $settlement = (string) $request->query('settlement', 'all');

        return [
            'scope' => $request->query('scope') === self::SCOPE_OWN ? self::SCOPE_OWN : self::SCOPE_SUBAGENTS,
            'agent' => (string) $request->query('agent', 'all'),
            'subagent' => (string) $request->query('subagent', 'all'),
            'settlement' => in_array($settlement, self::SETTLEMENTS, true) ? $settlement : 'all',
            'q' => trim((string) $request->query('q', '')),
            'problems' => $request->boolean('problems'),
            'profit_only' => $request->boolean('profit_only'),
            'range' => (string) $request->query('range', 'all'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    /**
     * @param  array{scope: string, agent: string, subagent: string, settlement: string, q: string, problems: bool, profit_only: bool}  $filters
     * @return Builder<Order>
     */
    public function query(Request $request, array $filters): Builder
    {
        [$from, $to] = DateRange::resolve($request);
        $search = $filters['q'];
        $own = $filters['scope'] === self::SCOPE_OWN;

        $query = Order::query()
            ->where('orders.seller_type', ($own ? new Agent : new Subagent)->getMorphClass())
            ->where(fn (Builder $q) => $q->where('orders.payment_status', Order::PAYMENT_PAID)->orWhereHas('earnings'))
            ->when($filters['agent'] !== 'all', fn (Builder $q) => $own
                ? $q->where('orders.seller_id', (int) $filters['agent'])
                : $q->whereIn('orders.seller_id', Subagent::query()->select('id')->where('agent_id', (int) $filters['agent'])))
            ->when(! $own && $filters['subagent'] !== 'all', fn (Builder $q) => $q->where('orders.seller_id', (int) $filters['subagent']))
            ->when($filters['profit_only'], fn (Builder $q) => $q->whereColumn('orders.customer_price', '>', 'orders.seller_cost'))
            ->when($filters['settlement'] !== 'all', fn (Builder $q) => $q->whereHas(
                'earnings',
                fn (Builder $e) => $e->where('status', $filters['settlement']),
            ))
            ->when($from, fn (Builder $q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('orders.created_at', '<=', $to))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('orders.reference', 'like', "%{$search}%")
                ->orWhereHasMorph('seller', [$own ? Agent::class : Subagent::class], fn (Builder $s) => $s
                    ->where('name', 'like', "%{$search}%")
                    ->when(! $own, fn (Builder $n) => $n->orWhereHas('agent', fn (Builder $a) => $a->where('name', 'like', "%{$search}%"))))));

        if ($filters['problems']) {
            $this->applyIssueFilter($query, $own);
        }

        return $query;
    }

    /**
     * Totals cover money still in play — failed/refunded sales are listed but not summed.
     *
     * @param  Builder<Order>  $query
     * @return array{sales: int, customerPaid: float, sellerCost: float, subagentShare: float, agentShare: float, platformShare: float, dbhCost: float, profitCredited: float, profitPending: float, problems: int}
     */
    public function stats(Builder $query, bool $own = false): array
    {
        $totals = (clone $query)
            ->whereNotIn('orders.status', [Order::STATUS_FAILED, Order::STATUS_REFUNDED])
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw('COALESCE(SUM(customer_price), 0) as customer_paid')
            ->selectRaw('COALESCE(SUM(seller_cost), 0) as seller_cost')
            ->selectRaw('COALESCE(SUM(customer_price - seller_cost), 0) as subagent_share')
            ->selectRaw('COALESCE(SUM(seller_cost - agent_cost), 0) as agent_share')
            ->selectRaw('COALESCE(SUM(agent_cost - COALESCE(upstream_cost, base_cost, agent_cost)), 0) as platform_share')
            ->selectRaw('COALESCE(SUM(COALESCE(upstream_cost, base_cost, agent_cost)), 0) as dbh_cost')
            ->toBase()
            ->first();

        // Earnings rows themselves, so these match the Shop profit column on the Resellers table.
        $profit = Earning::query()
            ->whereIn('order_id', (clone $query)->select('orders.id'))
            ->where('type', Earning::TYPE_SHOP_PROFIT)
            ->groupBy('status')
            ->selectRaw('status, SUM(amount) as total')
            ->toBase()
            ->pluck('total', 'status');

        $problems = clone $query;
        $this->applyIssueFilter($problems, $own);

        return [
            'sales' => (int) $totals->sales,
            'customerPaid' => round((float) $totals->customer_paid, 2),
            'sellerCost' => round((float) $totals->seller_cost, 2),
            'subagentShare' => round((float) $totals->subagent_share, 2),
            'agentShare' => round((float) $totals->agent_share, 2),
            'platformShare' => round((float) $totals->platform_share, 2),
            'dbhCost' => round((float) $totals->dbh_cost, 2),
            'profitCredited' => round((float) ($profit[Earning::STATUS_CREDITED] ?? 0), 2),
            'profitPending' => round((float) ($profit[Earning::STATUS_PENDING] ?? 0), 2),
            'problems' => $problems->count(),
        ];
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query->with([
            'seller' => fn ($seller) => $seller->morphWith([Subagent::class => ['agent:id,name']]),
            'earnings',
        ]);
    }

    /** @param  Builder<Order>  $query */
    private function applyIssueFilter(Builder $query, bool $own): void
    {
        $own ? $this->auditor->whereOwnSaleHasIssue($query) : $this->auditor->whereHasIssue($query);
    }

    /** @return array<string, mixed> */
    public function present(Order $order): array
    {
        $shares = $this->auditor->shares($order);
        $statuses = $order->earnings->pluck('status');

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'date' => $order->created_at?->format('j M Y, H:i'),
            'subagent' => $order->seller?->name ?? '—',
            'agent' => ($order->seller instanceof Subagent ? $order->seller->agent?->name : $order->seller?->name) ?? '—',
            'orderStatus' => $order->status,
            'customerPaid' => (float) $order->customer_price,
            'sellerCost' => (float) $order->seller_cost,
            'subagentShare' => $shares['subagent'],
            'agentShare' => $shares['agent'],
            'platformShare' => $shares['platform'],
            'dbhCost' => $shares['dbh'],
            'dbhActual' => $shares['dbhActual'],
            'settlement' => match (true) {
                $statuses->contains(Earning::STATUS_CREDITED) => Earning::STATUS_CREDITED,
                $statuses->contains(Earning::STATUS_PENDING) => Earning::STATUS_PENDING,
                $statuses->contains(Earning::STATUS_REVERSED) => Earning::STATUS_REVERSED,
                default => 'none',
            },
            'issues' => $this->auditor->issues($order),
        ];
    }

    /**
     * Every agent with their subagents, for the filter dropdowns.
     *
     * @return list<array{id: int, name: string, subagents: list<array{id: int, name: string}>}>
     */
    public function agentOptions(): array
    {
        return Agent::query()
            ->with(['subagents' => fn ($s) => $s->orderBy('name')->select(['id', 'agent_id', 'name'])])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Agent $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'subagents' => $a->subagents->map(fn (Subagent $s): array => ['id' => $s->id, 'name' => $s->name])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
