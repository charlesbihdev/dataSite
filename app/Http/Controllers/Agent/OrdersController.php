<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $user->orders()->latest();

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('reference', 'like', "%{$request->q}%")
                    ->orWhere('beneficiary_phone', 'like', "%{$request->q}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
        }

        $ordersCount = (clone $query)->count();
        $totalSales = (clone $query)->where('payment_status', 'paid')->sum('customer_price');
        $totalProfit = (clone $query)->where('payment_status', 'paid')
            ->selectRaw('SUM(customer_price - seller_cost) as profit')
            ->value('profit') ?? 0;

        $orders = $query->paginate(15)->withQueryString();

        return Inertia::render('agent/orders', [
            'orders' => $orders,
            'stats' => [
                'count' => $ordersCount,
                'sales' => (float) $totalSales,
                'profit' => (float) $totalProfit,
            ],
            'filters' => $request->only(['q', 'date_from', 'date_to']),
        ]);
    }
}
