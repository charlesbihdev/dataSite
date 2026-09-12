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

        $phone = $this->input($request, ['phoneNumber', 'beneficiary_number', 'phone']);
        $network = strtolower($this->input($request, ['network']));
        $capacityRaw = $this->input($request, ['capacity', 'package_size', 'data_gb', 'gb']);

        if ($phone === '' || $network === '' || $capacityRaw === '') {
            return $this->error('Missing required fields: phoneNumber, network, capacity.', 'MISSING_FIELD');
        }
        if (! preg_match('/^0[0-9]{9}$/', $phone)) {
            return $this->error('Invalid phone number. Use format 0551234567.', 'INVALID_PHONE');
        }
        if (! in_array($network, self::NETWORKS, true)) {
            return $this->error('Unsupported network. Use one of: '.implode(', ', self::NETWORKS).'.', 'INVALID_NETWORK');
        }

        $capacity = (int) $capacityRaw;
        if ($capacity < 1 || $capacity > 200) {
            return $this->error('Invalid capacity. Whole GB between 1 and 200.', 'INVALID_CAPACITY');
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

    public function show(Request $request, string $reference): JsonResponse
    {
        /** @var Agent|Subagent $seller */
        $seller = $request->attributes->get('api_seller');

        $order = $seller->orders()->where('reference', $reference)->first();
        if ($order === null) {
            return $this->error('Order not found.', 'ORDER_NOT_FOUND', 404);
        }

        return response()->json(['success' => true, 'data' => $this->present($order)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order, bool $duplicate = false): array
    {
        return [
            'reference' => $order->reference,
            'idempotencyKey' => $order->idempotency_key,
            'network' => strtoupper($order->network),
            'capacity' => (int) $order->capacity_gb,
            'phoneNumber' => $order->beneficiary_phone,
            'price' => (float) $order->seller_cost,
            'orderStatus' => $order->status,
            'isCompleted' => $order->status === Order::STATUS_COMPLETED,
            'failureReason' => $order->failure_reason,
            'duplicate' => $duplicate,
            'createdAt' => $order->created_at?->toIso8601String(),
        ];
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
