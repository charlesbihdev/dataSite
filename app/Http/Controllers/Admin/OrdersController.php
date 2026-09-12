<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderSettlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin view of every order: the frozen cascade, who earned what, and upstream state.
 * Read-first; the one write action is re-queuing a status poll for a stuck processing order.
 */
class OrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');
        $network = (string) $request->query('network', 'all');
        $search = trim((string) $request->query('q', ''));
        $range = (string) $request->query('range', 'all');
        [$from, $to] = $this->resolveDateRange($request, $range);

        // Everything except the status dropdown — shared by the table and the summary cards,
        // so switching status doesn't zero out the breakdown.
        $scoped = fn () => Order::query()
            ->when($network !== 'all', fn ($q) => $q->where('network', $network))
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search): void {
                $inner->where('reference', 'like', "%{$search}%")
                    ->orWhere('beneficiary_phone', 'like', "%{$search}%");
            }));

        $orders = $scoped()
            ->with('seller')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order): array => $this->present($order));

        return Inertia::render('admin/orders', [
            'orders' => $orders,
            'stats' => $this->stats($scoped),
            'filters' => [
                'status' => $status,
                'network' => $network,
                'q' => $search,
                'range' => $range,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    /**
     * Summary cards for the filtered scope. We only surface what maps to our model: the four
     * statuses + refunded, revenue, success rate, and today. (Databundleshub's "rejected" is
     * folded into failed here, and its API/dashboard source split has no equivalent for us.)
     *
     * @param  callable(): Builder<Order>  $scoped
     * @return array<string, int|float|null>
     */
    private function stats(callable $scoped): array
    {
        $byStatus = $scoped()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $byStatus->sum();
        $completed = (int) ($byStatus[Order::STATUS_COMPLETED] ?? 0);
        $failed = (int) ($byStatus[Order::STATUS_FAILED] ?? 0);

        return [
            'total' => $total,
            'pending' => (int) ($byStatus[Order::STATUS_PENDING] ?? 0),
            'processing' => (int) ($byStatus[Order::STATUS_PROCESSING] ?? 0),
            'completed' => $completed,
            'failed' => $failed,
            'refunded' => (int) ($byStatus[Order::STATUS_REFUNDED] ?? 0),
            'revenue' => (float) $scoped()->where('status', Order::STATUS_COMPLETED)->sum('customer_price'),
            'successRate' => $total > 0 ? round($completed / $total * 100, 1) : null,
            'todayCount' => (int) $scoped()->whereDate('created_at', today())->count(),
            'todayRevenue' => (float) $scoped()
                ->where('status', Order::STATUS_COMPLETED)
                ->whereDate('created_at', today())
                ->sum('customer_price'),
        ];
    }

    /**
     * Translate a range preset (or a custom from/to pair) into concrete datetime bounds.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveDateRange(Request $request, string $range): array
    {
        $now = now();

        return match ($range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'custom' => [
                $this->parseDate((string) $request->query('from'))?->startOfDay(),
                $this->parseDate((string) $request->query('to'))?->endOfDay(),
            ],
            default => [null, null],
        };
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Re-queue a status poll for a processing order (Databundleshub is poll-only).
     */
    public function poll(Order $order): RedirectResponse
    {
        if ($order->status === Order::STATUS_PROCESSING && $order->upstream_request_id !== null) {
            PollUpstreamOrderStatus::dispatch($order->id);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Status poll queued for {$order->reference}."]);
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only processing orders with an upstream reference can be polled.']);
        }

        return back();
    }

    /**
     * Admin refund of a settled order: return the seller's deposit and reverse the earnings.
     * Only a delivered order can be refunded here — pending/processing orders settle or reverse
     * through the upstream pipe, and failed/already-refunded orders gave the money back already.
     */
    public function refund(Request $request, Order $order, OrderSettlementService $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($order->status !== Order::STATUS_COMPLETED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only completed orders can be refunded.']);

            return back();
        }

        $result = $settlement->refund($order, $validated['reason'] ?? null);

        Inertia::flash('toast', [
            'type' => $result['refunded'] ? 'success' : 'error',
            'message' => $result['refunded']
                ? "{$order->reference} refunded — GHS ".number_format($result['amount'], 2).' returned to the seller.'
                : $result['message'],
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order): array
    {
        $customer = (float) $order->customer_price;
        $seller = (float) $order->seller_cost;
        $agent = (float) $order->agent_cost;
        $base = $order->base_cost !== null ? (float) $order->base_cost : null;
        $sellerModel = $order->seller;

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'seller' => ($sellerModel?->name ?? 'Unknown').' ('.class_basename($order->seller_type).')',
            'network' => strtoupper($order->network),
            'capacityGb' => (float) $order->capacity_gb,
            'beneficiary' => $order->beneficiary_phone,
            'channel' => $order->channel,
            'status' => $order->status,
            'cascade' => [
                'customerPrice' => $customer,
                'sellerCost' => $seller,
                'agentCost' => $agent,
                'baseCost' => $base,
                'sellerProfit' => round($customer - $seller, 2),
                'agentCommission' => round($seller - $agent, 2),
                'platformProfit' => $base !== null ? round($agent - $base, 2) : null,
            ],
            'upstream' => [
                'requestId' => $order->upstream_request_id,
                'reference' => $order->upstream_reference,
                'status' => $order->upstream_status,
                'cost' => $order->upstream_cost !== null ? (float) $order->upstream_cost : null,
                'lastPolledAt' => $order->last_polled_at?->format('M j, H:i'),
                'failureReason' => $order->failure_reason,
            ],
            'createdAt' => $order->created_at?->format('M j, Y H:i'),
        ];
    }
}
