<?php

namespace App\Services\Resellers;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The admin Agents & Subagents view: each agent that has recruited subagents, the subagents under
 * them, and what their sales earned each side. Every figure is built per subagent from two grouped
 * queries (delivered sales, earnings by type/status), then rolled up to the agent.
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
            ->whereHas('subagents')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $matches($w)->orWhereHas('subagents', $matches)))
            ->with(['subagents' => fn ($s) => $s->orderBy('name')])
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $figures = $this->figures($page->getCollection()->flatMap->subagents->pluck('id'), $from, $to);

        return $page->through(function (Agent $agent) use ($figures): array {
            $subagents = $agent->subagents->map(fn (Subagent $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'username' => $s->username,
                'phone' => $s->phone,
                'isActive' => (bool) $s->is_active,
            ] + ($figures[$s->id] ?? $this->blank()));

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'username' => $agent->username,
                'phone' => $agent->phone,
                'isActive' => (bool) $agent->is_active,
                'subagentCount' => $subagents->count(),
                'subagents' => $subagents->values()->all(),
            ] + collect(array_keys($this->blank()))
                ->mapWithKeys(fn (string $key): array => [$key => round((float) $subagents->sum($key), 2)])
                ->all();
        });
    }

    /**
     * Platform-wide headline figures for the period (ignores the search box).
     *
     * @return array{agents: int, subagents: int, agentEarned: float, subagentEarned: float, pending: float}
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
            'agents' => Agent::query()->whereHas('subagents')->count(),
            'subagents' => Subagent::query()->count(),
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

        $sales = Order::query()
            ->where('seller_type', (new Subagent)->getMorphClass())
            ->whereIn('seller_id', $subagentIds)
            ->where('status', Order::STATUS_COMPLETED)
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to))
            ->groupBy('seller_id')
            ->selectRaw('seller_id, COUNT(*) as sales, SUM(customer_price) as revenue')
            ->toBase()
            ->get()
            ->keyBy('seller_id');

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

    /** @return Builder<Earning> */
    private function earningsQuery(?Carbon $from, ?Carbon $to): Builder
    {
        return Earning::query()
            ->join('orders', 'orders.id', '=', 'earnings.order_id')
            ->where('orders.seller_type', (new Subagent)->getMorphClass())
            ->whereIn('earnings.status', [Earning::STATUS_PENDING, Earning::STATUS_CREDITED])
            ->when($from, fn (Builder $q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('orders.created_at', '<=', $to));
    }

    /** @return array<string, float|int> */
    private function blank(): array
    {
        return ['sales' => 0, 'revenue' => 0.0, 'subagentEarned' => 0.0, 'subagentPending' => 0.0, 'agentEarned' => 0.0, 'agentPending' => 0.0];
    }
}
