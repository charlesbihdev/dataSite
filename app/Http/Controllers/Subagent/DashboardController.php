<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent portal home (D2, auth:subagent). A lean dashboard shell: wallet balance, their sales,
 * matured earnings, and recent orders. Mirrors the agent dashboard's shape but scoped to the
 * subagent's own records via the subagent guard.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $subagent = $request->user('subagent');

        $recentOrders = $subagent->orders()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'reference' => $order->reference,
                'network' => $order->network,
                'capacityGb' => (float) $order->capacity_gb,
                'customerPrice' => (float) $order->customer_price,
                'status' => $order->status,
                'createdAt' => $order->created_at?->diffForHumans(),
            ]);

        return Inertia::render('subagent/dashboard', [
            'stats' => [
                'walletBalance' => (float) ($subagent->wallet?->balance ?? 0),
                'ordersCount' => $subagent->orders()->count(),
                'salesRevenue' => (float) $subagent->orders()
                    ->where('payment_status', Order::PAYMENT_PAID)
                    ->sum('customer_price'),
                'earnings' => (float) $subagent->earnings()
                    ->where('status', Earning::STATUS_CREDITED)
                    ->sum('amount'),
            ],
            'recentOrders' => $recentOrders,
        ]);
    }
}
