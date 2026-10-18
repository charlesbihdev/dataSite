<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Concerns\HandlesOrderActions;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderDispatchService;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's Orders page — their own storefront sales. Reuses the shared HandlesOrderActions
 * trait for payment verification and dispatch retries, strictly scoped to their own orders.
 */
class OrdersController extends Controller
{
    use HandlesOrderActions;

    public function index(Request $request): Response
    {
        $subagent = $request->user('subagent');

        $range = (string) $request->query('range', 'all');
        [$from, $to] = DateRange::resolve($request, $range);
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $network = (string) $request->query('network', 'all');

        $query = $subagent->orders()
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

        // Aggregate off a paid-only clone (money is secured once paid). Profit = margin the subagent
        // keeps = sale price − their cost (seller_cost snapshot).
        $paid = (clone $query)->where('payment_status', 'paid');
        $totalSales = $paid->sum('customer_price');
        $totalProfit = $totalSales - $paid->sum('seller_cost');

        $orders = $query->paginate(30)->withQueryString();

        return Inertia::render('subagent/orders', [
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

    /**
     * Let a subagent re-send one of their OWN failed storefront orders.
     */
    public function retry(Request $request, int $order, OrderDispatchService $dispatch): RedirectResponse
    {
        /** @var Order $found */
        $found = $request->user('subagent')->orders()->whereKey($order)->firstOrFail();

        return $this->performOrderRetry($found, $dispatch);
    }

    /**
     * Let a subagent verify a customer's gateway payment on one of their OWN storefront orders and dispatch it.
     */
    public function verifyPayment(Request $request, int $order, OrderPaymentConfirmer $confirmer): RedirectResponse
    {
        /** @var Order $found */
        $found = $request->user('subagent')->orders()->whereKey($order)->firstOrFail();

        return $this->performOrderPaymentVerification($found, $confirmer);
    }
}
