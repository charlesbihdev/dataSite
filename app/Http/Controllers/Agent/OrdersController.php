<?php

namespace App\Http\Controllers\Agent;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderDispatchService;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Services\Payments\PaymentVerifier;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Let an agent re-send one of their OWN failed orders. Scoped to the agent's orders so they can
     * never touch another seller's; redispatch re-debits the wallet for a prepaid order (money no
     * longer held after the failure) and simply re-sends a customer-paid storefront order.
     */
    public function retry(Request $request, int $order, OrderDispatchService $dispatch): RedirectResponse
    {
        /** @var Order $found */
        $found = $request->user()->orders()->whereKey($order)->firstOrFail();

        if ($found->status !== Order::STATUS_FAILED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only failed orders can be retried.']);

            return back();
        }

        try {
            $dispatch->redispatch($found);
        } catch (InsufficientBalanceException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Insufficient wallet balance to retry this order. Top up and try again.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$found->reference} is being re-sent."]);

        return back();
    }

    /**
     * Let an agent verify a customer's gateway payment on one of their OWN storefront orders and, if
     * it cleared, dispatch it. Reuses the shared OrderPaymentConfirmer (same path as the storefront
     * callback, the webhooks, and the admin verify) so the money outcome is identical everywhere.
     */
    public function verifyPayment(Request $request, int $order, OrderPaymentConfirmer $confirmer): RedirectResponse
    {
        /** @var Order $found */
        $found = $request->user()->orders()->whereKey($order)->firstOrFail();

        if ($found->payment_status !== Order::PAYMENT_AWAITING) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only orders awaiting payment can be verified.']);

            return back();
        }

        $toast = match ($confirmer->confirm($found)) {
            PaymentVerifier::PAID => ['type' => 'success', 'message' => "Payment confirmed for {$found->reference}. Bundle dispatched."],
            PaymentVerifier::FAILED => ['type' => 'error', 'message' => "{$found->reference}: the payment failed. Order marked failed."],
            default => ['type' => 'info', 'message' => "{$found->reference}: payment not confirmed yet. Try again shortly."],
        };

        Inertia::flash('toast', $toast);

        return back();
    }
}
