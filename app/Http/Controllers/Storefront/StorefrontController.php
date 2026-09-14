<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\Agent;
use App\Models\AgentPackagePrice;
use App\Models\Order;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Services\Payments\OrderPaymentInitiator;
use App\Services\Storefront\CheckoutException;
use App\Services\Storefront\StorefrontCheckoutService;
use App\Support\GhanaMobileNetwork;
use App\Support\SurfaceUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public agent storefront at /buy/{slug} (D2). A walk-in customer picks one of the agent's active
 * packages and pays through a gateway — the order is created AWAITING and only fulfilled once that
 * payment clears (verified from the admin backoffice). No auth: this is the customer-facing shop.
 */
class StorefrontController extends Controller
{
    public function show(string $slug): Response
    {
        $agent = $this->resolveAgent($slug);

        // Count the visit for the agent's referral stats (best-effort, storefront is the buy link).
        $agent->increment('referral_clicks');

        return Inertia::render('agent/storefront/buy', [
            'agentSlug' => $slug,
            'store' => $this->storeProps($agent),
            'packages' => $this->packages($agent),
            'networks' => GhanaMobileNetwork::meta(),
            // Recruitment is the MIDDLE rung of the ladder and lives ONLY on this D2 domain
            // (ARCHITECTURE, LADDER RULE) — a visitor here may become this agent's sub-agent.
            'recruitUrl' => $this->recruitUrl($agent),
        ]);
    }

    public function checkout(CheckoutRequest $request, string $slug, StorefrontCheckoutService $checkout, OrderPaymentInitiator $payments): RedirectResponse
    {
        $agent = $this->resolveAgent($slug);

        try {
            $order = $checkout->checkout(
                $agent,
                (string) $request->input('beneficiary_phone'),
                (string) $request->input('network'),
                (int) $request->integer('capacity_gb'),
            );

            $payment = $payments->initiate(
                $order,
                route('agent.storefront.callback', ['agentSlug' => $slug]),
            );
        } catch (CheckoutException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        // Hand the customer off to the gateway's hosted checkout; they return to the callback below.
        return Inertia::location($payment['authorization_url']);
    }

    public function paymentCallback(Request $request, string $slug, OrderPaymentConfirmer $confirmer): RedirectResponse
    {
        $agent = $this->resolveAgent($slug);
        $reference = trim((string) $request->query('reference', ''));

        $order = $agent->orders()
            ->where('source', Order::SOURCE_STOREFRONT)
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('gateway_reference', $reference))
            ->first();

        if ($order !== null) {
            $confirmer->confirm($order);
        }

        return to_route('agent.storefront.receipt', [
            'agentSlug' => $slug,
            'order' => $order?->reference ?? $reference,
        ]);
    }

    public function receipt(string $slug, string $order): Response
    {
        $agent = $this->resolveAgent($slug);

        $found = $agent->orders()->where('reference', $order)->firstOrFail();

        return Inertia::render('agent/storefront/receipt', [
            'agentSlug' => $slug,
            'store' => $this->storeProps($agent),
            'order' => [
                'reference' => $found->reference,
                'network' => $found->network,
                'networkLabel' => GhanaMobileNetwork::label($found->network),
                'capacityGb' => (int) $found->capacity_gb,
                'phone' => $found->beneficiary_phone,
                'amount' => (float) $found->customer_price,
                'paymentStatus' => $found->payment_status,
                'status' => $found->status,
            ],
        ]);
    }

    public function track(Request $request, string $slug): Response
    {
        $agent = $this->resolveAgent($slug);

        // Customers look up their own orders either by the number they paid for or by an order
        // reference from their receipt. Both are scoped to THIS agent's storefront orders only.
        $by = $request->query('by') === 'reference' ? 'reference' : 'phone';
        $phone = GhanaMobileNetwork::normalize((string) $request->query('phone', ''));
        $reference = strtoupper(trim((string) $request->query('reference', '')));

        $query = $agent->orders()->where('source', 'storefront');

        if ($by === 'reference' && $reference !== '') {
            $query->where('reference', $reference);
        } elseif ($by === 'phone' && $phone !== '') {
            $query->where('beneficiary_phone', $phone);
        } else {
            $query = null;
        }

        $orders = $query
            ? $query->latest()
                ->limit(20)
                ->get()
                ->map(fn ($o): array => [
                    'reference' => $o->reference,
                    'network' => $o->network,
                    'networkLabel' => GhanaMobileNetwork::label($o->network),
                    'capacityGb' => (float) $o->capacity_gb,
                    'phone' => $o->beneficiary_phone,
                    'amount' => (float) $o->customer_price,
                    'paymentStatus' => $o->payment_status,
                    'status' => $o->status,
                    'date' => $o->created_at?->format('M j, Y g:i A'),
                ])
                ->all()
            : [];

        return Inertia::render('agent/storefront/track', [
            'agentSlug' => $slug,
            'store' => $this->storeProps($agent),
            'by' => $by,
            'phone' => $phone,
            'reference' => $reference,
            'orders' => $orders,
        ]);
    }

    /** Resolve a live storefront by slug: the agent must exist, be active, and have their store on. */
    private function resolveAgent(string $slug): Agent
    {
        $agent = Agent::query()->where('slug', $slug)->firstOrFail();

        abort_unless($agent->is_active && $agent->store_active, 404);

        return $agent;
    }

    /**
     * The sub-agent recruitment link for this agent (D2 only). Mirrors SubagentsController: signing up
     * through it makes the visitor this agent's sub-agent. Path-prefixed in local dev; domain in prod.
     */
    private function recruitUrl(Agent $agent): string
    {
        $ref = $agent->slug ?? (string) $agent->getKey();

        return SurfaceUrl::to('agent_store', "/register?ref={$ref}");
    }

    /**
     * @return array{name: string, whatsapp: string|null, whatsappGroup: string|null}
     */
    private function storeProps(Agent $agent): array
    {
        return [
            'name' => $agent->store_name ?: $agent->name,
            'whatsapp' => $agent->whatsapp_number,
            'whatsappGroup' => $agent->whatsapp_group_link,
        ];
    }

    /**
     * The active packages the customer may buy, cheapest first within each network.
     *
     * @return list<array{id: int, network: string, networkLabel: string|null, capacityGb: int, price: float}>
     */
    private function packages(Agent $agent): array
    {
        return $agent->packagePrices()
            ->where('is_active', true)
            ->orderBy('network')
            ->orderBy('capacity_gb')
            ->get()
            ->map(fn (AgentPackagePrice $p): array => [
                'id' => $p->id,
                'network' => $p->network,
                'networkLabel' => GhanaMobileNetwork::label($p->network),
                'capacityGb' => $p->capacity_gb,
                'price' => (float) $p->selling_price,
            ])
            ->all();
    }
}
