<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Support\DateRange;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(Request $request)
    {
        $agent = $request->user();

        // The date filter scopes only what varies over time — orders and revenue.
        // Wallet balance and subagent count are point-in-time facts, so they stay
        // always-current regardless of the selected range.
        // Default to the last 30 days (matches the admin overview) rather than
        // all-time, so the dashboard opens on a meaningful recent window.
        $range = (string) $request->query('range', 'last_30_days');
        [$from, $to] = DateRange::resolve($request, $range);

        $inRange = fn () => $agent->orders()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $revenue = (float) (clone $inRange())
            ->where('status', Order::STATUS_COMPLETED)
            ->sum('customer_price');

        $recentOrders = $inRange()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'network' => $order->network,
                'capacityGb' => (float) $order->capacity_gb,
                'customerPrice' => (float) $order->customer_price,
                'status' => $order->status,
                'createdAt' => $order->created_at?->diffForHumans(),
            ]);

        return Inertia::render('dashboard', [
            'filters' => [
                'range' => $range,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
            'stats' => [
                'ordersCount' => (clone $inRange())->count(),
                'revenue' => $revenue,
                // Always-current — the date filter does not touch these.
                'walletBalance' => (float) ($agent->wallet?->balance ?? 0),
                'subagentsCount' => $agent->subagents()->count(),
            ],
            'recentOrders' => $recentOrders,
            // The agent's public storefront link, resolved via the named route so it is correct in
            // both prod (domain) and local (path-prefix) modes — never hand-built on the client.
            'storeUrl' => route('agent.storefront', ['agentSlug' => $agent->slug ?? (string) $agent->id]),
            // Place-Order card + Cart panel (always-current, independent of the date filter).
            'cart' => $this->cart->items(),
            'cartTotal' => $this->cart->total(),
            'networks' => GhanaMobileNetwork::meta(),
        ]);
    }
}
