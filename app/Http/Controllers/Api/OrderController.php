<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Subagent;
use App\Services\Orders\NewOrderData;
use App\Services\Orders\OrderDispatchService;
use App\Services\Pricing\PriceQuote;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Developer API for placing and tracking orders. A thin authenticated layer over
 * OrderDispatchService: we resolve the caller's price from their tier, then push to
 * Databundleshub synchronously (no intake queue — dispatch is idempotent and the poll job
 * handles the async tail). Request/response shapes mirror Databundleshub's create_order /
 * purchase-status so an agent's existing DBH integration ports across with minimal change.
 */
class OrderController extends Controller
{
    private const NETWORKS = ['mtn', 'telecel', 'at'];

    public function store(Request $request, PriceQuote $quote, OrderDispatchService $dispatch): JsonResponse
    {
        /** @var Agent|Subagent $seller */
        $seller = $request->attributes->get('api_seller');

        $rawPhone = $this->input($request, ['phoneNumber', 'beneficiary_number', 'phone']);
        $phone = GhanaMobileNetwork::normalize($rawPhone);
        if ($phone === '') {
            return $this->error('Invalid phone number. Use format 0551234567.', 'INVALID_PHONE');
        }

        $rawNetwork = strtolower($this->input($request, ['network']));
        $network = self::normalizeNetwork($rawNetwork) ?? GhanaMobileNetwork::detect($phone);

        if ($network === null || ! in_array($network, self::NETWORKS, true)) {
            return $this->error('Unsupported network. Use one of: '.implode(', ', self::NETWORKS).'.', 'INVALID_NETWORK');
        }

        $capacityRaw = $this->input($request, ['capacity', 'package_size', 'data_gb', 'gb']);
        $digitsOnly = preg_replace('/\D+/', '', $capacityRaw);
        if ($digitsOnly === '') {
            return $this->error('Missing required fields: capacity.', 'MISSING_FIELD');
        }

        $capacity = (int) $digitsOnly;
        if ($capacityError = GhanaMobileNetwork::validateOrder($phone, $capacity)) {
            return $this->error($capacityError, 'INVALID_CAPACITY');
        }

        // Idempotency: a client key (header or body), else derived from the sale shape. A repeat
        // returns the original order instead of charging again.
        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotencyKey', '')));
        if ($idempotencyKey === '') {
            $idempotencyKey = hash('sha256', $seller->getMorphClass().$seller->getKey().$phone.$network.$capacity);
        }

        $existing = $seller->orders()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return response()->json(['success' => true, 'data' => $this->present($existing, true)]);
        }

        $price = $quote->for($seller, $network, $capacity);
        if ($price === null) {
            return $this->error('No price is configured for this network and capacity.', 'INVALID_PRICING');
        }

        // For an API sale the caller buys at their own tier rate — no retail markup layered by us,
        // so customer_price = seller_cost. Platform still profits on (agent_cost − base_cost).
        $agentCost = $seller instanceof Subagent
            ? ($quote->for($seller->agent, $network, $capacity)['amount'] ?? $price['amount'])
            : $price['amount'];

        try {
            $order = $dispatch->dispatch(new NewOrderData(
                seller: $seller,
                network: $network,
                capacityGb: $capacity,
                beneficiaryPhone: $phone,
                customerPrice: $price['amount'],
                sellerCost: $price['amount'],
                agentCost: $agentCost,
                baseCost: $price['baseCost'],
                source: 'api',
                idempotencyKey: $idempotencyKey,
            ));
        } catch (InsufficientBalanceException) {
            return $this->error('Insufficient balance at purchase time.', 'INSUFFICIENT_BALANCE', 409);
        }

        return response()->json(['success' => true, 'data' => $this->present($order)], 201);
    }

    public function show(Request $request, ?string $reference = null): JsonResponse
    {
        /** @var Agent|Subagent $seller */
        $seller = $request->attributes->get('api_seller');

        $ref = $reference ?: $this->input($request, ['reference', 'order_id', 'orderId', 'purchase_id', 'purchaseId']);
        if ($ref === '') {
            return $this->error('Order reference is required.', 'MISSING_REFERENCE', 400);
        }

        $order = $seller->orders()
            ->where(function ($query) use ($ref) {
                $query->where('reference', $ref)
                    ->orWhere('id', is_numeric($ref) ? (int) $ref : 0);
            })
            ->first();

        if ($order === null) {
            return $this->error('Order not found.', 'ORDER_NOT_FOUND', 404);
        }

        return response()->json(['success' => true, 'data' => $this->present($order)]);
    }

    public function packages(Request $request, PriceQuote $quote): JsonResponse
    {
        /** @var Agent|Subagent $seller */
        $seller = $request->attributes->get('api_seller');

        $networkFilter = strtolower(trim((string) $request->query('network', '')));
        if ($networkFilter !== '') {
            $normalized = self::normalizeNetwork($networkFilter);
            if ($normalized === null) {
                return $this->error('Unsupported network. Use one of: '.implode(', ', self::NETWORKS).'.', 'INVALID_NETWORK');
            }
            $networks = [$normalized];
        } else {
            $networks = self::NETWORKS;
        }

        $items = [];

        foreach ($networks as $network) {
            $sizes = GhanaMobileNetwork::packageSizesGb($network);
            foreach ($sizes as $size) {
                $price = $quote->for($seller, $network, $size);
                if ($price === null) {
                    continue;
                }
                $items[] = [
                    'capacity' => (string) $size,
                    'mb' => (string) ($size * 1024),
                    'price' => number_format($price['amount'], 2, '.', ''),
                    'network' => strtoupper($network),
                    'pricePerGB' => number_format($price['pricePerGb'], 2, '.', ''),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'totalPackages' => count($items),
                'supportedNetworks' => ['MTN', 'TELECEL', 'AT'],
            ],
        ]);
    }

    private static function normalizeNetwork(string $code): ?string
    {
        return match ($code) {
            'mtn', 'yello' => 'mtn',
            'telecel', 'vodafone' => 'telecel',
            'at', 'airteltigo' => 'at',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order, bool $duplicate = false): array
    {
        // Mirror Databundleshub's response shape: a general per-state note lives in `message`, and
        // failure detail only appears on a genuine failure — never on a held/pending order, which
        // simply awaits dispatch. This keeps a ported DBH integration reading the same fields.
        $isFailed = $order->status === Order::STATUS_FAILED;

        return [
            'reference' => $order->reference,
            'idempotencyKey' => $order->idempotency_key,
            'network' => strtoupper($order->network),
            'capacity' => (int) $order->capacity_gb,
            'phoneNumber' => $order->beneficiary_phone,
            'price' => (float) $order->seller_cost,
            'orderStatus' => $order->status,
            'isCompleted' => $order->status === Order::STATUS_COMPLETED,
            'message' => $this->statusMessage($order, $duplicate),
            'failureReason' => $isFailed ? $order->failure_reason : null,
            'errorCode' => $isFailed ? 'FULFILLMENT_FAILED' : null,
            'duplicate' => $duplicate,
            'createdAt' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * Human-readable, per-state note matching Databundleshub's `message` field. A held (pending)
     * order surfaces its "awaiting supplier" note here — not in a failure field.
     */
    private function statusMessage(Order $order, bool $duplicate): string
    {
        return match ($order->status) {
            Order::STATUS_COMPLETED => 'Order delivered successfully.',
            Order::STATUS_FAILED => $order->failure_reason ?: 'Order could not be completed.',
            Order::STATUS_REFUNDED => $order->failure_reason ?: 'Order was refunded.',
            default => $duplicate
                ? 'Duplicate request detected. Returning the existing order.'
                : ($order->failure_reason ?: 'Order is being processed. Check the status endpoint shortly.'),
        };
    }

    private function input(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $request->input($key);
            if ($value !== null && $value !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function error(string $message, string $code, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message, 'code' => $code], $status);
    }
}
