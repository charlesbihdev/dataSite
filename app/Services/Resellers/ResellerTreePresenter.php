<?php

namespace App\Services\Resellers;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use stdClass;

/**
 * The admin Agents & Subagents view: every agent, the subagents under them, and each person's own
 * money. Sales and earnings honour the date filter; withdrawn/available are always all-time, because
 * they describe what someone holds today and match HasEarnings::earningsBalance().
 */
final class ResellerTreePresenter
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function agents(string $search, ?Carbon $from, ?Carbon $to): LengthAwarePaginator
    {
        $like = "%{$search}%";
        $matches = fn (Builder $q) => $q->where('name', 'like', $like)
            ->orWhere('username', 'like', $like)
            ->orWhere('phone', 'like', $like);

        $page = Agent::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $matches($w)->orWhereHas('subagents', $matches)))
            ->with(['subagents' => fn ($s) => $s->orderBy('name')])
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $agentIds = $page->getCollection()->pluck('id');
        $subagentIds = $page->getCollection()->flatMap->subagents->pluck('id');

        $figures = $this->figures($subagentIds, $from, $to);
        $own = $this->ownShop($agentIds, $from, $to);
        $agentMoney = $this->money((new Agent)->getMorphClass(), $agentIds);
        $subagentMoney = $this->money((new Subagent)->getMorphClass(), $subagentIds);

        return $page->through(function (Agent $agent) use ($figures, $own, $agentMoney, $subagentMoney): array {
            $subagents = $agent->subagents->map(fn (Subagent $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'username' => $s->username,
                'phone' => $s->phone,
                'isActive' => (bool) $s->is_active,
                'totalEarned' => (float) ($figures[$s->id]['subagentEarned'] ?? 0),
            ] + ($subagentMoney[$s->id] ?? $this->noMoney()) + ($figures[$s->id] ?? $this->blank()));

            $rollup = collect(array_keys($this->blank()))
                ->mapWithKeys(fn (string $key): array => [$key => round((float) $subagents->sum($key), 2)])
                ->all();
            $shop = $own[$agent->id] ?? ['ownSales' => 0, 'ownRevenue' => 0.0, 'ownProfit' => 0.0, 'ownPending' => 0.0];

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'username' => $agent->username,
                'phone' => $agent->phone,
                'isActive' => (bool) $agent->is_active,
                'subagentCount' => $subagents->count(),
                'subagents' => $subagents->values()->all(),
                'totalEarned' => round($shop['ownProfit'] + $rollup['agentEarned'], 2),
            ] + $shop + ($agentMoney[$agent->id] ?? $this->noMoney()) + $rollup;
        });
    }

    /**
     * Platform-wide headline figures for the period (ignores the search box).
     *
     * @return array{agents: int, subagents: int, agentShopProfit: float, agentEarned: float, subagentEarned: float, pending: float}
     */
    public function stats(?Carbon $from, ?Carbon $to): array
    {
        $earnings = $this->earningsQuery($from, $to)
            ->selectRaw('earnings.type, earnings.status, SUM(earnings.amount) as total')
            ->groupBy('earnings.type', 'earnings.status')
            ->toBase()
            ->get();

        $sum = fn (?string $type, string $status): float => round((float) $earnings
            ->filter(fn ($row) => ($type === null || $row->type === $type) && $row->status === $status)
            ->sum('total'), 2);

        return [
            'agents' => Agent::query()->count(),
            'subagents' => Subagent::query()->count(),
            'agentShopProfit' => round((float) $this->earningsQuery($from, $to, Agent::class)
                ->where('earnings.type', Earning::TYPE_SHOP_PROFIT)
                ->where('earnings.status', Earning::STATUS_CREDITED)
                ->sum('earnings.amount'), 2),
            'agentEarned' => $sum(Earning::TYPE_COMMISSION, Earning::STATUS_CREDITED),
            'subagentEarned' => $sum(Earning::TYPE_SHOP_PROFIT, Earning::STATUS_CREDITED),
            'pending' => $sum(null, Earning::STATUS_PENDING),
        ];
    }

    /**
     * @param  Collection<int, int>  $subagentIds
     * @return array<int, array<string, float|int>>
     */
    private function figures(Collection $subagentIds, ?Carbon $from, ?Carbon $to): array
    {
        if ($subagentIds->isEmpty()) {
            return [];
        }

        $sales = $this->deliveredSales(Subagent::class, $subagentIds, $from, $to);

        $earnings = $this->earningsQuery($from, $to)
            ->whereIn('orders.seller_id', $subagentIds)
            ->selectRaw('orders.seller_id, earnings.type, earnings.status, SUM(earnings.amount) as total')
            ->groupBy('orders.seller_id', 'earnings.type', 'earnings.status')
            ->toBase()
            ->get()
            ->groupBy('seller_id');

        return $subagentIds->mapWithKeys(function (int $id) use ($sales, $earnings): array {
            $rows = $earnings->get($id, collect());
            $sum = fn (string $type, string $status): float => round((float) $rows
                ->where('type', $type)->where('status', $status)->sum('total'), 2);

            return [$id => [
                'sales' => (int) ($sales[$id]->sales ?? 0),
                'revenue' => round((float) ($sales[$id]->revenue ?? 0), 2),
                'subagentEarned' => $sum(Earning::TYPE_SHOP_PROFIT, Earning::STATUS_CREDITED),
                'subagentPending' => $sum(Earning::TYPE_SHOP_PROFIT, Earning::STATUS_PENDING),
                'agentEarned' => $sum(Earning::TYPE_COMMISSION, Earning::STATUS_CREDITED),
                'agentPending' => $sum(Earning::TYPE_COMMISSION, Earning::STATUS_PENDING),
            ]];
        })->all();
    }

    /**
     * What each agent sold and earned themselves, from their own shop and portal/API orders.
     *
     * @param  Collection<int, int>  $agentIds
     * @return array<int, array{ownSales: int, ownRevenue: float, ownProfit: float, ownPending: float}>
     */
    private function ownShop(Collection $agentIds, ?Carbon $from, ?Carbon $to): array
    {
        if ($agentIds->isEmpty()) {
            return [];
        }

        $sales = $this->deliveredSales(Agent::class, $agentIds, $from, $to);

        $earnings = $this->earningsQuery($from, $to, Agent::class)
            ->whereIn('orders.seller_id', $agentIds)
            ->where('earnings.type', Earning::TYPE_SHOP_PROFIT)
            ->selectRaw('orders.seller_id, earnings.status, SUM(earnings.amount) as total')
            ->groupBy('orders.seller_id', 'earnings.status')
            ->toBase()
            ->get()
            ->groupBy('seller_id');

        return $agentIds->mapWithKeys(function (int $id) use ($sales, $earnings): array {
            $rows = $earnings->get($id, collect());

            return [$id => [
                'ownSales' => (int) ($sales[$id]->sales ?? 0),
                'ownRevenue' => round((float) ($sales[$id]->revenue ?? 0), 2),
                'ownProfit' => round((float) $rows->where('status', Earning::STATUS_CREDITED)->sum('total'), 2),
                'ownPending' => round((float) $rows->where('status', Earning::STATUS_PENDING)->sum('total'), 2),
            ]];
        })->all();
    }

    /**
     * All-time payouts and balance per earner, in bulk (same sum as earningsBalance()).
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, array{withdrawn: float, requested: float, available: float}>
     */
    private function money(string $morphClass, Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $credited = Earning::query()
            ->where('earner_type', $morphClass)
            ->whereIn('earner_id', $ids)
            ->where('status', Earning::STATUS_CREDITED)
            ->groupBy('earner_id')
            ->selectRaw('earner_id, SUM(amount) as total')
            ->toBase()
            ->pluck('total', 'earner_id');

        $withdrawals = Withdrawal::query()
            ->where('earner_type', $morphClass)
            ->whereIn('earner_id', $ids)
            ->whereIn('status', [Withdrawal::STATUS_PENDING, Withdrawal::STATUS_APPROVED, Withdrawal::STATUS_PAID])
            ->groupBy('earner_id', 'status')
            ->selectRaw('earner_id, status, SUM(amount) as total')
            ->toBase()
            ->get()
            ->groupBy('earner_id');

        return $ids->mapWithKeys(function (int $id) use ($credited, $withdrawals): array {
            $rows = $withdrawals->get($id, collect());
            $paid = (float) $rows->where('status', Withdrawal::STATUS_PAID)->sum('total');
            $requested = (float) $rows->where('status', '!=', Withdrawal::STATUS_PAID)->sum('total');

            return [$id => [
                'withdrawn' => round($paid, 2),
                'requested' => round($requested, 2),
                'available' => round((float) ($credited[$id] ?? 0) - $paid - $requested, 2),
            ]];
        })->all();
    }

    /**
     * @param  class-string<Agent|Subagent>  $sellerClass
     * @param  Collection<int, int>  $ids
     * @return Collection<int|string, stdClass>
     */
    private function deliveredSales(string $sellerClass, Collection $ids, ?Carbon $from, ?Carbon $to): Collection
    {
        return Order::query()
            ->where('seller_type', (new $sellerClass)->getMorphClass())
            ->whereIn('seller_id', $ids)
            ->where('status', Order::STATUS_COMPLETED)
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to))
            ->groupBy('seller_id')
            ->selectRaw('seller_id, COUNT(*) as sales, SUM(customer_price) as revenue')
            ->toBase()
            ->get()
            ->keyBy('seller_id');
    }

    /**
     * @param  class-string<Agent|Subagent>  $sellerClass
     * @return Builder<Earning>
     */
    private function earningsQuery(?Carbon $from, ?Carbon $to, string $sellerClass = Subagent::class): Builder
    {
        return Earning::query()
            ->join('orders', 'orders.id', '=', 'earnings.order_id')
            ->where('orders.seller_type', (new $sellerClass)->getMorphClass())
            ->whereIn('earnings.status', [Earning::STATUS_PENDING, Earning::STATUS_CREDITED])
            ->when($from, fn (Builder $q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('orders.created_at', '<=', $to));
    }

    /** @return array{withdrawn: float, requested: float, available: float} */
    private function noMoney(): array
    {
        return ['withdrawn' => 0.0, 'requested' => 0.0, 'available' => 0.0];
    }

    /** @return array<string, float|int> */
    private function blank(): array
    {
        return ['sales' => 0, 'revenue' => 0.0, 'subagentEarned' => 0.0, 'subagentPending' => 0.0, 'agentEarned' => 0.0, 'agentPending' => 0.0];
    }
}
