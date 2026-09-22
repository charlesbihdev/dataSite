<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\Order;
use App\Models\Subagent;
use App\Models\SubagentPackagePrice;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Services\Payments\OrderPaymentInitiator;
use App\Services\Storefront\CheckoutException;
use App\Services\Storefront\SubagentCheckoutService;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The public D3 subagent storefront at /{subagentSlug}. The sealed BOTTOM of the ladder: buy-only, no
 * recruitment, no link back up (ARCHITECTURE). A walk-in customer picks one of the subagent's active
 * packages and pays through a gateway — the order is created AWAITING and fulfilled once payment clears.
 * Mirrors the agent StorefrontController with the recruitment path removed.
 */
class SubagentStorefrontController extends Controller
{
    public function show(string $slug): Response
    {
        $subagent = $this->resolveSubagent($slug);

        $subagent->increment('referral_clicks');

        return Inertia::render('subagent/storefront/buy', [
            'subagentSlug' => $slug,
            'store' => $this->storeProps($subagent),
            'packages' => $this->packages($subagent),
            'networks' => GhanaMobileNetwork::meta(),
        ]);
    }

    public function checkout(CheckoutRequest $request, string $slug, SubagentCheckoutService $checkout, OrderPaymentInitiator $payments): HttpResponse
    {
        $subagent = $this->resolveSubagent($slug);

        try {
            $order = $checkout->checkout(
                $subagent,
                (string) $request->input('beneficiary_phone'),
                (string) $request->input('network'),
                (int) $request->integer('capacity_gb'),
            );

            $payment = $payments->initiate(
                $order,
                route('subagent.storefront.callback', ['subagentSlug' => $slug]),
            );
        } catch (CheckoutException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        return Inertia::location($payment['authorization_url']);
    }

    public function paymentCallback(Request $request, string $slug, OrderPaymentConfirmer $confirmer): RedirectResponse
    {
        $subagent = $this->resolveSubagent($slug);
        $reference = trim((string) $request->query('reference', ''));

        $order = $subagent->orders()
            ->where('source', Order::SOURCE_STOREFRONT)
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('gateway_reference', $reference))
            ->first();

        if ($order !== null) {
            $confirmer->confirm($order);
        }

        return to_route('subagent.storefront.receipt', [
            'subagentSlug' => $slug,
            'order' => $order?->reference ?? $reference,
        ]);
    }

    public function receipt(string $slug, string $order): Response
    {
        $subagent = $this->resolveSubagent($slug);

        $found = $subagent->orders()->where('reference', $order)->firstOrFail();

        return Inertia::render('subagent/storefront/receipt', [
            'subagentSlug' => $slug,
            'store' => $this->storeProps($subagent),
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
        $subagent = $this->resolveSubagent($slug);

        $by = $request->query('by') === 'reference' ? 'reference' : 'phone';
        $phone = GhanaMobileNetwork::normalize((string) $request->query('phone', ''));
        $reference = strtoupper(trim((string) $request->query('reference', '')));

        $query = $subagent->orders()->where('source', 'storefront');

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

        return Inertia::render('subagent/storefront/track', [
            'subagentSlug' => $slug,
            'store' => $this->storeProps($subagent),
            'by' => $by,
            'phone' => $phone,
            'reference' => $reference,
            'orders' => $orders,
        ]);
    }

    /**
     * Resolve a live storefront by its username-derived handle (slug) or username — never the id, so
     * the public link is always /{username}. The subagent must exist, be active, and have their store on.
     */
    private function resolveSubagent(string $slug): Subagent
    {
        $subagent = Subagent::query()
            ->where('slug', $slug)
            ->orWhere('username', $slug)
            ->first();

        abort_unless($subagent !== null && $subagent->is_active && $subagent->store_active, 404);

        return $subagent;
    }

    /**
     * @return array{name: string, whatsapp: string|null, whatsappGroup: string|null}
     */
    private function storeProps(Subagent $subagent): array
    {
        return [
            'name' => $subagent->store_name ?: $subagent->name,
            'whatsapp' => $subagent->whatsapp_number,
            'whatsappGroup' => $subagent->whatsapp_group_link,
        ];
    }

    /**
     * The active packages the customer may buy, cheapest first within each network.
     *
     * @return list<array{id: int, network: string, networkLabel: string|null, capacityGb: int, price: float}>
     */
    private function packages(Subagent $subagent): array
    {
        return $subagent->packagePrices()
            ->where('is_active', true)
            ->orderBy('network')
            ->orderBy('capacity_gb')
            ->get()
            ->map(fn (SubagentPackagePrice $p): array => [
                'id' => $p->id,
                'network' => $p->network,
                'networkLabel' => GhanaMobileNetwork::label($p->network),
                'capacityGb' => $p->capacity_gb,
                'price' => (float) $p->selling_price,
            ])
            ->all();
    }
}
