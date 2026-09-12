<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Subagent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin backoffice landing: the money-and-orders overview.
 *
 * Revenue/profit are summed over COMPLETED orders only from the frozen cascade, so the
 * figures never shift when prices are later edited (decision #6) — the opposite of how DBH
 * recomputes profit live. Platform profit = agent_cost − base_cost, the superadmin's cut.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => $this->stats(),
            'byNetwork' => $this->byNetwork(),
            'recentOrders' => $this->recentOrders(),
        ]);
    }

    /**
     * Revenue + volume per network over completed orders (the "reports" widget lives here,
     * not in a separate section — mirrors how DBH keeps reporting on the dashboard).
     *
     * @return list<array<string, mixed>>
     */
    private function byNetwork(): array
    {
        return Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->selectRaw('network, COUNT(*) as orders, SUM(customer_price) as revenue, '
                .'SUM(agent_cost) - SUM(upstream_cost) as profit')
            ->groupBy('network')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row): array => [
                'network' => strtoupper((string) $row->network),
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
                'profit' => (float) $row->profit,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $completed = Order::query()->where('status', Order::STATUS_COMPLETED);

        // All money figures reference ACTUAL supplier cost (upstream_cost) so the cards reconcile:
        //   revenue − supplierCost = grossProfit (the whole chain's margin)
        //   grossProfit = platformProfit + resellerEarnings (agents + subagents)
        $revenue = (float) (clone $completed)->sum('customer_price');
        $supplierCost = (float) (clone $completed)->sum('upstream_cost');
        $agentCharges = (float) (clone $completed)->sum('agent_cost');

        $grossProfit = round($revenue - $supplierCost, 2);
        $platformProfit = round($agentCharges - $supplierCost, 2);

        return [
            'revenue' => $revenue,
            'supplierCost' => $supplierCost,
            'grossProfit' => $grossProfit,
            'platformProfit' => $platformProfit,
            'resellerEarnings' => round($grossProfit - $platformProfit, 2),
            'orders' => [
                'total' => Order::query()->count(),
                'completed' => (clone $completed)->count(),
                'processing' => Order::query()->where('status', Order::STATUS_PROCESSING)->count(),
                'failed' => Order::query()->where('status', Order::STATUS_FAILED)->count(),
            ],
            'agents' => Agent::query()->count(),
            'subagents' => Subagent::query()->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Order::query()
            ->with('seller')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'reference' => $order->reference,
                'seller' => $this->sellerLabel($order),
                'network' => strtoupper($order->network),
                'capacityGb' => (float) $order->capacity_gb,
                'beneficiary' => $order->beneficiary_phone,
                'customerPrice' => (float) $order->customer_price,
                'baseCost' => $order->base_cost !== null ? (float) $order->base_cost : null,
                'upstreamCost' => $order->upstream_cost !== null ? (float) $order->upstream_cost : null,
                'status' => $order->status,
                'createdAt' => $order->created_at?->format('M j, H:i'),
            ])
            ->all();
    }

    private function sellerLabel(Order $order): string
    {
        $seller = $order->seller;
        $name = $seller?->name ?? 'Unknown';
        $type = class_basename($order->seller_type);

        return "{$name} ({$type})";
    }
}
