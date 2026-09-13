<?php

namespace App\Services\Orders;

use App\Models\Agent;
use App\Models\Order;
use App\Models\Subagent;
use App\Support\DateRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Builds the paginator, summary cards, and echoed filters for the admin order lists. One table,
 * two segments: AGENT covers seller-placed orders (portal + API), REGULAR covers public storefront
 * orders. The segment fixes the base scope; the rest of the filters are shared. Kept out of the
 * controller so both order pages stay thin (mirrors AccountsPresenter).
 */
class OrderListPresenter
{
    public const SEGMENT_AGENT = 'agent';

    public const SEGMENT_REGULAR = 'regular';

    /**
     * @return array{orders: LengthAwarePaginator, stats: array<string, int|float|null>, filters: array<string, mixed>}
     */
    public function listing(Request $request, string $segment): array
    {
        $status = (string) $request->query('status', 'all');
        $network = (string) $request->query('network', 'all');
        $seller = (string) $request->query('seller', 'all');
        $source = (string) $request->query('source', 'all');
        $payment = (string) $request->query('payment', 'all');
        $search = trim((string) $request->query('q', ''));
        $range = (string) $request->query('range', 'all');
        [$from, $to] = DateRange::resolve($request, $range);

        // Everything except the status dropdown — shared by the table and the summary cards, so
        // switching status doesn't zero out the breakdown.
        $scoped = fn () => $this->scopedQuery($request, $segment);

        $orders = $scoped()
            ->with('seller')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Order $order): array => $this->present($order));

        return [
            'orders' => $orders,
            'stats' => $this->stats($scoped),
            'filters' => [
                'status' => $status,
                'network' => $network,
                'seller' => $seller,
                'source' => $source,
                'payment' => $payment,
                'q' => $search,
                'range' => $range,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ];
    }

    /**
     * The filtered query for a segment (everything except the status dropdown), rebuilt fresh on
     * each call. Public so exports reuse the exact same scope the list is showing.
     *
     * @return Builder<Order>
     */
    public function scopedQuery(Request $request, string $segment): Builder
    {
        $network = (string) $request->query('network', 'all');
        $seller = (string) $request->query('seller', 'all');
        $source = (string) $request->query('source', 'all');
        $payment = (string) $request->query('payment', 'all');
        $search = trim((string) $request->query('q', ''));
        [$from, $to] = DateRange::resolve($request, (string) $request->query('range', 'all'));

        $sellerType = match ($seller) {
            'agent' => Agent::class,
            'subagent' => Subagent::class,
            default => null,
        };

        return $this->segmentScope($segment)
            ->when($network !== 'all', fn ($q) => $q->where('network', $network))
            ->when($segment === self::SEGMENT_AGENT && $sellerType !== null, fn ($q) => $q->where('seller_type', $sellerType))
            ->when($segment === self::SEGMENT_AGENT && in_array($source, [Order::SOURCE_PORTAL, Order::SOURCE_API], true), fn ($q) => $q->where('source', $source))
            ->when($segment === self::SEGMENT_REGULAR && $payment !== 'all', fn ($q) => $q->where('payment_status', $payment))
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search): void {
                $inner->where('reference', 'like', "%{$search}%")
                    ->orWhere('beneficiary_phone', 'like', "%{$search}%")
                    ->orWhere('upstream_request_id', 'like', "%{$search}%")
                    ->orWhere('upstream_reference', 'like', "%{$search}%")
                    ->orWhereHasMorph('seller', [Agent::class, Subagent::class], function ($seller) use ($search): void {
                        $seller->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            }));
    }

    /**
     * The base scope for a segment: agent = seller-placed (portal/API), regular = public storefront.
     *
     * @return Builder<Order>
     */
    private function segmentScope(string $segment): Builder
    {
        return $segment === self::SEGMENT_REGULAR
            ? Order::query()->where('source', Order::SOURCE_STOREFRONT)
            : Order::query()->whereIn('source', [Order::SOURCE_PORTAL, Order::SOURCE_API]);
    }

    /**
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

        return [
            'total' => $total,
            'pending' => (int) ($byStatus[Order::STATUS_PENDING] ?? 0),
            'processing' => (int) ($byStatus[Order::STATUS_PROCESSING] ?? 0),
            'completed' => $completed,
            'failed' => (int) ($byStatus[Order::STATUS_FAILED] ?? 0),
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
     * @return array<string, mixed>
     */
    private function present(Order $order): array
    {
        $customer = (float) $order->customer_price;
        $seller = (float) $order->seller_cost;
        $agent = (float) $order->agent_cost;
        $base = $order->base_cost !== null ? (float) $order->base_cost : null;

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'seller' => $order->seller?->name ?? 'Unknown',
            'sellerType' => class_basename($order->seller_type),
            'network' => strtoupper($order->network),
            'capacityGb' => (float) $order->capacity_gb,
            'beneficiary' => $order->beneficiary_phone,
            'channel' => $order->channel,
            'source' => $order->source,
            'paymentStatus' => $order->payment_status,
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
