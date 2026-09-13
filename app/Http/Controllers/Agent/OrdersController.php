<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Shares the DateRangePicker's ?range/from/to contract with the agent
        // dashboard, resolved once through DateRange so both surfaces filter identically.
        $range = (string) $request->query('range', 'all');
        [$from, $to] = DateRange::resolve($request, $range);
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $network = (string) $request->query('network', 'all');

        $query = $user->orders()
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $like = "%{$search}%";
                $inner->where('reference', 'like', $like)
                    ->orWhere('beneficiary_phone', 'like', $like)
                    ->orWhere('upstream_reference', 'like', $like)
                    ->orWhere('network', 'like', $like)
                    ->orWhere('status', 'like', $like);
            }))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($network !== 'all', fn ($q) => $q->where('network', $network))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest();

        $ordersCount = (clone $query)->count();
        $totalSales = (clone $query)->where('payment_status', 'paid')->sum('customer_price');
        $totalProfit = (clone $query)->where('payment_status', 'paid')
            ->selectRaw('SUM(customer_price - seller_cost) as profit')
            ->value('profit') ?? 0;

        $orders = $query->paginate(30)->withQueryString();

        return Inertia::render('agent/orders', [
            'orders' => $orders,
            'stats' => [
                'count' => $ordersCount,
                'sales' => (float) $totalSales,
                'profit' => (float) $totalProfit,
            ],
            'filters' => [
                'range' => $range,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'q' => $search,
                'status' => $status,
                'network' => $network,
            ],
        ]);
    }
}
