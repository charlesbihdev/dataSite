<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent portal home — a sales/earnings dashboard (they sell only through their storefront):
 * available-to-withdraw with a 7-day trend, their storefront link, recent sales, top sellers, and a
 * 7-day summary (volume, revenue, margin, unique buyers).
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $subagent = $request->user('subagent');
        $weekStart = now()->subDays(7);

        $storefront = $subagent->orders()->where('source', Order::SOURCE_STOREFRONT);
        $paidWeek = (clone $storefront)->where('payment_status', Order::PAYMENT_PAID)->where('created_at', '>=', $weekStart);

        return Inertia::render('subagent/dashboard', [
            'greeting' => [
                'name' => $subagent->name,
                'resellerOf' => $subagent->agent?->name,
            ],
            'earnings' => [
                'available' => $subagent->earningsBalance(),
                'today' => (float) $subagent->earnings()->where('status', Earning::STATUS_CREDITED)->whereDate('credited_at', now()->toDateString())->sum('amount'),
                'lifetime' => (float) $subagent->earnings()->where('status', Earning::STATUS_CREDITED)->sum('amount'),
                'pending' => (float) $subagent->earnings()->where('status', Earning::STATUS_PENDING)->sum('amount'),
                'trend' => $this->earningsTrend($subagent),
            ],
            'store' => [
                'url' => route('subagent.storefront', ['subagentSlug' => $subagent->slug ?? $subagent->username ?? (string) $subagent->id]),
                'active' => (bool) $subagent->store_active,
                'hasLink' => $subagent->slug !== null || $subagent->username !== null,
            ],
            'thisWeek' => [
                'salesVolume' => (clone $paidWeek)->count(),
                'revenue' => (float) (clone $paidWeek)->sum('customer_price'),
                'margin' => (float) (clone $paidWeek)->selectRaw('COALESCE(SUM(customer_price - seller_cost), 0) as m')->value('m'),
                'uniqueBuyers' => (int) (clone $paidWeek)->distinct('beneficiary_phone')->count('beneficiary_phone'),
            ],
            'recentSales' => (clone $storefront)->latest()->limit(5)->get()->map(fn (Order $o): array => [
                'id' => $o->id,
                'reference' => $o->reference,
                'network' => $o->network,
                'capacityGb' => (float) $o->capacity_gb,
                'beneficiaryPhone' => $o->beneficiary_phone,
                'customerPrice' => (float) $o->customer_price,
                'status' => $o->status,
                'createdAt' => $o->created_at?->diffForHumans(),
            ]),
            'topSellers' => (clone $storefront)->where('created_at', '>=', $weekStart)
                ->selectRaw('network, capacity_gb, COUNT(*) as sales, COALESCE(SUM(customer_price), 0) as revenue')
                ->groupBy('network', 'capacity_gb')
                ->orderByDesc('sales')
                ->limit(5)
                ->get()
                ->map(fn ($row): array => [
                    'network' => $row->network,
                    'capacityGb' => (float) $row->capacity_gb,
                    'sales' => (int) $row->sales,
                    'revenue' => (float) $row->revenue,
                ]),
        ]);
    }

    /**
     * Credited earnings per day for the last 7 days (oldest → newest) — the dashboard sparkline.
     *
     * @return list<float>
     */
    private function earningsTrend($subagent): array
    {
        return collect(range(6, 0))->map(function (int $daysAgo) use ($subagent): float {
            $day = now()->subDays($daysAgo)->toDateString();

            return (float) $subagent->earnings()
                ->where('status', Earning::STATUS_CREDITED)
                ->whereDate('credited_at', $day)
                ->sum('amount');
        })->all();
    }
}
