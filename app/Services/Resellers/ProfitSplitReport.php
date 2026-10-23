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
 * The admin Profit Splits list: every subagent sale that reached settlement (paid, or already
 * holding earnings rows — a refund flips paid back to awaiting but keeps the reversed earnings),
 * with each party's share and the auditor's verdict.
 */
final class ProfitSplitReport
{
    public const SETTLEMENTS = ['all', Earning::STATUS_CREDITED, Earning::STATUS_PENDING, Earning::STATUS_REVERSED];

    public function __construct(private readonly ProfitSplitAuditor $auditor) {}

    /**
     * @return array{agent: string, subagent: string, settlement: string, q: string, problems: bool, range: string, from: ?string, to: ?string}
     */
    public function filters(Request $request): array
    {
        $settlement = (string) $request->query('settlement', 'all');

        return [
            'agent' => (string) $request->query('agent', 'all'),
            'subagent' => (string) $request->query('subagent', 'all'),
            'settlement' => in_array($settlement, self::SETTLEMENTS, true) ? $settlement : 'all',
            'q' => trim((string) $request->query('q', '')),
            'problems' => $request->boolean('problems'),
            'range' => (string) $request->query('range', 'all'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    /**
     * @param  array{agent: string, subagent: string, settlement: string, q: string, problems: bool}  $filters
     * @return Builder<Order>
     */
    public function query(Request $request, array $filters): Builder
    {
        [$from, $to] = DateRange::resolve($request);
        $search = $filters['q'];

        $query = Order::query()
            ->where('orders.seller_type', (new Subagent)->getMorphClass())
            ->where(fn (Builder $q) => $q->where('orders.payment_status', Order::PAYMENT_PAID)->orWhereHas('earnings'))
            ->when($filters['agent'] !== 'all', fn (Builder $q) => $q->whereIn(
                'orders.seller_id',
                Subagent::query()->select('id')->where('agent_id', (int) $filters['agent']),
            ))
            ->when($filters['subagent'] !== 'all', fn (Builder $q) => $q->where('orders.seller_id', (int) $filters['subagent']))
            ->when($filters['settlement'] !== 'all', fn (Builder $q) => $q->whereHas(
                'earnings',
                fn (Builder $e) => $e->where('status', $filters['settlement']),
            ))
            ->when($from, fn (Builder $q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('orders.created_at', '<=', $to))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('orders.reference', 'like', "%{$search}%")
                ->orWhereHasMorph('seller', [Subagent::class], fn (Builder $s) => $s
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('agent', fn (Builder $a) => $a->where('name', 'like', "%{$search}%")))));

        if ($filters['problems']) {
            $this->auditor->whereHasIssue($query);
        }

        return $query;
    }

    /**
     * Totals cover money still in play — failed/refunded sales are listed but not summed.
     *
     * @param  Builder<Order>  $query
     * @return array{sales: int, customerPaid: float, subagentShare: float, agentShare: float, platformShare: float, dbhCost: float, problems: int}
     */
    public function stats(Builder $query): array
    {
        $totals = (clone $query)
            ->whereNotIn('orders.status', [Order::STATUS_FAILED, Order::STATUS_REFUNDED])
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw('COALESCE(SUM(customer_price), 0) as customer_paid')
            ->selectRaw('COALESCE(SUM(customer_price - seller_cost), 0) as subagent_share')
            ->selectRaw('COALESCE(SUM(seller_cost - agent_cost), 0) as agent_share')
            ->selectRaw('COALESCE(SUM(agent_cost - COALESCE(upstream_cost, base_cost, agent_cost)), 0) as platform_share')
            ->selectRaw('COALESCE(SUM(COALESCE(upstream_cost, base_cost, agent_cost)), 0) as dbh_cost')
            ->toBase()
            ->first();

        $problems = clone $query;
        $this->auditor->whereHasIssue($problems);

        return [
            'sales' => (int) $totals->sales,
            'customerPaid' => round((float) $totals->customer_paid, 2),
            'subagentShare' => round((float) $totals->subagent_share, 2),
            'agentShare' => round((float) $totals->agent_share, 2),
            'platformShare' => round((float) $totals->platform_share, 2),
            'dbhCost' => round((float) $totals->dbh_cost, 2),
            'problems' => $problems->count(),
        ];
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query->with(['seller' => fn ($s) => $s->with('agent:id,name'), 'earnings']);
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
            'agent' => $order->seller?->agent?->name ?? '—',
            'orderStatus' => $order->status,
            'customerPaid' => (float) $order->customer_price,
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
     * Agents that have subagents, each with their subagents, for the filter dropdowns.
     *
     * @return list<array{id: int, name: string, subagents: list<array{id: int, name: string}>}>
     */
    public function agentOptions(): array
    {
        return Agent::query()
            ->whereHas('subagents')
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
