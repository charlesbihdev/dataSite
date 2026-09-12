<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = $request->query('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->query('date_to', now()->format('Y-m-d'));

        // 1. Data Served & Revenue (Completed only)
        $dataServed = Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('SUM(capacity_gb) as total_gb, COUNT(*) as total_orders, SUM(customer_price) as total_revenue')
            ->first();

        // 2. Daily Orders Trend
        $dailyOrders = Order::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = '" . Order::STATUS_COMPLETED . "' THEN 1 ELSE 0 END) as completed_orders")
            ->selectRaw("SUM(CASE WHEN status = '" . Order::STATUS_FAILED . "' THEN 1 ELSE 0 END) as failed_orders")
            ->selectRaw('SUM(customer_price) as total_revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->get();

        // 3. Daily Top-ups Trend
        $dailyTopups = WalletTransaction::query()
            ->where('type', 'topup')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as amount, COUNT(*) as count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->get();

        // 4. Top Agents Leaderboard
        $topAgents = Order::query()
            ->where('orders.status', Order::STATUS_COMPLETED)
            ->whereDate('orders.created_at', '>=', $dateFrom)
            ->whereDate('orders.created_at', '<=', $dateTo)
            ->where('orders.seller_type', Agent::class)
            ->join('agents', 'orders.seller_id', '=', 'agents.id')
            ->selectRaw('agents.name as agent, agents.email, COUNT(orders.id) as orders, SUM(orders.customer_price) as revenue')
            ->groupBy('agents.id', 'agents.name', 'agents.email')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return Inertia::render('admin/analytics', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'dataServed' => [
                'gb' => (float) ($dataServed->total_gb ?? 0),
                'orders' => (int) ($dataServed->total_orders ?? 0),
                'revenue' => (float) ($dataServed->total_revenue ?? 0),
            ],
            'dailyOrders' => $dailyOrders->map(fn($o) => [
                'date' => $o->date,
                'total_orders' => (int) $o->total_orders,
                'completed_orders' => (int) $o->completed_orders,
                'failed_orders' => (int) $o->failed_orders,
                'revenue' => (float) $o->total_revenue,
            ]),
            'dailyTopups' => $dailyTopups->map(fn($t) => [
                'date' => $t->date,
                'amount' => (float) $t->amount,
                'count' => (int) $t->count,
            ]),
            'topAgents' => $topAgents->map(fn($a) => [
                'agent' => $a->agent,
                'email' => $a->email,
                'orders' => (int) $a->orders,
                'revenue' => (float) $a->revenue,
            ]),
        ]);
    }
}
