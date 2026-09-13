<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Subagent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sub-agent sales: orders placed through this agent's sub-agents' storefronts. The agent earns the
 * commission cut (seller_cost − agent_cost, see ProfitSplit) on each. Read-only, scoped to sellers
 * that belong to the agent so one agent can never see another's sub-agent activity.
 */
class SubagentSalesController extends Controller
{
    public function index(Request $request): Response
    {
        $agent = $request->user();
        $subagents = $agent->subagents()->get(['id', 'name']);
        $subagentIds = $subagents->pluck('id');

        $subagent = (string) $request->query('subagent', 'all');
        $status = (string) $request->query('status', 'all');
        $network = (string) $request->query('network', 'all');
        $search = trim((string) $request->query('q', ''));

        $filtered = fn (): Builder => Order::query()
            ->where('seller_type', (new Subagent)->getMorphClass())
            ->whereIn('seller_id', $subagentIds)
            ->when($subagent !== 'all', fn ($q) => $q->where('seller_id', (int) $subagent))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($network !== 'all', fn ($q) => $q->where('network', $network))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $like = "%{$search}%";
                $inner->where('reference', 'like', $like)->orWhere('beneficiary_phone', 'like', $like);
            }));

        $orders = $filtered()
            ->with('seller:id,name')
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Order $o): array => [
                'id' => $o->id,
                'reference' => $o->reference,
                'subagent' => $o->seller?->name ?? '—',
                'customer' => $o->beneficiary_phone,
                'network' => $o->network,
                'capacityGb' => (float) $o->capacity_gb,
                'amount' => (float) $o->customer_price,
                'margin' => round((float) $o->seller_cost - (float) $o->agent_cost, 2),
                'status' => $o->status,
                'date' => $o->created_at?->format('j M Y, H:i'),
            ]);

        return Inertia::render('agent/subagent-sales', [
            'orders' => $orders,
            'subagents' => $subagents,
            'filters' => ['subagent' => $subagent, 'status' => $status, 'network' => $network, 'q' => $search],
            'stats' => [
                'total' => (clone $filtered())->count(),
                'delivered' => (clone $filtered())->where('status', Order::STATUS_COMPLETED)->count(),
                'placed' => (clone $filtered())->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING])->count(),
                // Margin earned from delivered sub-agent sales in the last 30 days (a fixed KPI).
                'margin30d' => (float) Order::query()
                    ->where('seller_type', (new Subagent)->getMorphClass())
                    ->whereIn('seller_id', $subagentIds)
                    ->where('status', Order::STATUS_COMPLETED)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->selectRaw('COALESCE(SUM(seller_cost - agent_cost), 0) as margin')
                    ->value('margin'),
            ],
        ]);
    }
}
