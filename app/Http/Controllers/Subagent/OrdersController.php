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
 * The subagent's Orders page — their storefront sales and their own Place-Order purchases, told
 * apart by source. Reuses the shared HandlesOrderActions
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
        $source = (string) $request->query('source', 'all');

        $query = $subagent->orders()
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $like = "%{$search}%";
                $inner->where('reference', 'like', $like)
                    ->orWhere('beneficiary_phone', 'like', $like)
                    ->orWhere('network', 'like', $like)
                    ->orWhere('status', 'like', $like);
            }))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($network !== 'all', fn ($q) => $q->where('network', $network))
            ->when(in_array($source, [Order::SOURCE_STOREFRONT, Order::SOURCE_PORTAL], true), fn ($q) => $q->where('source', $source))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest();

        $ordersCount = (clone $query)->count();

        // Revenue/profit count paid STOREFRONT sales only — a Place-Order purchase is the subagent
        // spending their own wallet, not a sale. Profit = sale price − their cost (seller_cost).
        $paid = (clone $query)->where('source', Order::SOURCE_STOREFRONT)->where('payment_status', 'paid');
        $totalSales = $paid->sum('customer_price');
        $totalProfit = $totalSales - $paid->sum('seller_cost');

        // Whitelist what the subagent sees. The raw row carries platform-internal fields
        // (base/agent/upstream cost, upstream refs/status) that must never reach their browser.
        $orders = $query->paginate(30)->withQueryString()->through(fn (Order $o): array => [
            'id' => $o->id,
            'reference' => $o->reference,
            'created_at' => $o->created_at,
            'source' => $o->source,
            'network' => $o->network,
            'capacity_gb' => $o->capacity_gb,
            'beneficiary_phone' => $o->beneficiary_phone,
            'customer_price' => $o->customer_price,
            'seller_cost' => $o->seller_cost,
            'payment_status' => $o->payment_status,
            'status' => $o->status,
        ]);

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
                'source' => $source,
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
