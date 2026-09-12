<?php

namespace App\Services\Accounts;

use App\Models\Agent;
use App\Models\Order;
use App\Models\Subagent;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the Accounts screen's rows, headline stats, and analytics from the agent/subagent
 * tables. Each row carries enough (orders count/total, last activity, wallet, earnings) for the
 * table, the details dialog, and the CSV export without extra round-trips.
 */
class AccountsPresenter
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(string $type, ?string $q, ?string $status): Collection
    {
        return $type === 'subagents'
            ? $this->subagentRows($q, $status)
            : $this->agentRows($q, $status);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function agentRows(?string $q, ?string $status): Collection
    {
        return Agent::query()
            ->with(['wallet', 'pricingTier'])
            ->withCount(['subagents', 'orders'])
            ->withSum('orders as orders_total', 'customer_price')
            ->withMax('orders as last_order_at', 'created_at')
            ->when($q, fn (Builder $b) => $this->applySearch($b, $q))
            ->when($status, fn (Builder $b) => $b->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->get()
            ->map(fn (Agent $a): array => $this->base($a) + [
                'detail' => ($a->pricingTier?->name ?? 'No tier').' · '.$a->subagents_count.' subagents',
                'canDelete' => $a->subagents_count === 0 && (int) $a->orders_count === 0,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function subagentRows(?string $q, ?string $status): Collection
    {
        return Subagent::query()
            ->with(['wallet', 'agent'])
            ->withCount(['orders'])
            ->withSum('orders as orders_total', 'customer_price')
            ->withMax('orders as last_order_at', 'created_at')
            ->when($q, fn (Builder $b) => $this->applySearch($b, $q))
            ->when($status, fn (Builder $b) => $b->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->get()
            ->map(fn (Subagent $s): array => $this->base($s) + [
                'detail' => 'Under '.($s->agent?->name ?? 'unknown agent'),
                'canDelete' => (int) $s->orders_count === 0,
            ]);
    }

    /**
     * Shared row shape for both account types.
     *
     * @return array<string, mixed>
     */
    private function base(Agent|Subagent $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'phone' => $model->phone,
            'email' => $model->email,
            'username' => $model->username,
            'wallet' => (float) ($model->wallet?->balance ?? 0),
            'earnings' => $model->earningsBalance(),
            'ordersCount' => (int) $model->orders_count,
            'ordersTotal' => round((float) ($model->orders_total ?? 0), 2),
            'lastActivity' => $model->last_order_at ? Carbon::parse($model->last_order_at)->format('M j, Y') : null,
            'createdAt' => $model->created_at?->format('M j, Y'),
            'status' => $model->is_active ? 'active' : 'suspended',
        ];
    }

    private function applySearch(Builder $query, ?string $q): Builder
    {
        $term = '%'.$q.'%';

        return $query->where(fn (Builder $b) => $b
            ->where('name', 'like', $term)
            ->orWhere('phone', 'like', $term)
            ->orWhere('username', 'like', $term)
            ->orWhere('email', 'like', $term));
    }

    /**
     * The headline cards, mirroring DBH's agent-management set. Computed over the whole tab (not
     * the filtered rows) so the totals stay stable as the admin searches.
     *
     * @return array<string, mixed>
     */
    public function stats(string $type): array
    {
        /** @var class-string<Agent|Subagent> $model */
        $model = $type === 'subagents' ? Subagent::class : Agent::class;
        $morph = (new $model)->getMorphClass();
        $orders = Order::query()->where('seller_type', $morph);

        return [
            'totalAccounts' => $model::query()->count(),
            'active30d' => $model::query()
                ->whereHas('orders', fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(30)))
                ->count(),
            'totalBalance' => round((float) Wallet::query()->where('walletable_type', $morph)->sum('balance'), 2),
            'totalOrders' => (clone $orders)->count(),
            'pendingOrders' => (clone $orders)->where('status', Order::STATUS_PROCESSING)->count(),
            'totalRevenue' => round((float) (clone $orders)->where('status', Order::STATUS_COMPLETED)->sum('customer_price'), 2),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function analytics(Collection $rows): array
    {
        $count = max($rows->count(), 1);

        return [
            'avgOrders' => round($rows->sum('ordersCount') / $count, 1),
            'avgRevenue' => round((float) $rows->sum('ordersTotal') / $count, 2),
            'activeRate' => round($rows->where('status', 'active')->count() / $count * 100, 1),
            'balance' => [
                'positive' => $rows->where('wallet', '>', 0)->count(),
                'zero' => $rows->where('wallet', '=', 0.0)->count(),
                'negative' => $rows->where('wallet', '<', 0)->count(),
            ],
            'topByOrders' => $rows->sortByDesc('ordersCount')->take(5)
                ->map(fn (array $r): array => [
                    'name' => $r['name'],
                    'ordersCount' => $r['ordersCount'],
                    'ordersTotal' => $r['ordersTotal'],
                ])->values()->all(),
        ];
    }
}
