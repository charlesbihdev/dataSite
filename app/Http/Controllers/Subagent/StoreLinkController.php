<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subagent\UpdateStoreContactRequest;
use App\Models\Order;
use App\Models\Subagent;
use App\Models\SubagentPackagePrice;
use App\Support\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's Store Link page — their customer-facing storefront link (D3 /{slug}), its QR,
 * performance (clicks/sales), the contact details shown at checkout, and the active packages
 * customers will see. Mirrors the agent's Store Link page; no recruitment (bottom rung).
 */
class StoreLinkController extends Controller
{
    public function index(Request $request): Response
    {
        $subagent = $request->user('subagent');
        $storeUrl = $this->storeUrl($this->handle($subagent));

        $storefront = $subagent->orders()->where('source', Order::SOURCE_STOREFRONT);
        $clicks = (int) $subagent->referral_clicks;
        $sales = (clone $storefront)->count();
        $revenue = (float) (clone $storefront)->where('payment_status', Order::PAYMENT_PAID)->sum('customer_price');

        return Inertia::render('subagent/store-link', [
            'referralUrl' => $storeUrl,
            'storeActive' => (bool) $subagent->store_active,
            // Deferred: the QR is the slow part, so the page renders immediately and it streams in.
            'referralQr' => Inertia::defer(fn () => $this->resolveQr($subagent, $storeUrl)),
            'contact' => [
                'store_name' => $subagent->store_name,
                'whatsapp_number' => $subagent->whatsapp_number,
                'whatsapp_group_link' => $subagent->whatsapp_group_link,
            ],
            'stats' => [
                'clicks' => $clicks,
                'sales' => $sales,
                'revenue' => $revenue,
                'activePackages' => $subagent->packagePrices()->where('is_active', true)->count(),
                'conversion' => $clicks > 0 ? round($sales / $clicks * 100, 1) : 0.0,
            ],
            'packages' => $subagent->packagePrices()->where('is_active', true)->latest('id')->get()->map(fn (SubagentPackagePrice $p): array => [
                'id' => $p->id,
                'network' => $p->network,
                'capacityGb' => $p->capacity_gb,
                'price' => (float) $p->selling_price,
                'profit' => round((float) $p->selling_price - (float) $p->cost_price, 2),
            ]),
        ]);
    }

    public function updateContact(UpdateStoreContactRequest $request): RedirectResponse
    {
        $request->user('subagent')->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Store contact details saved.']);

        return to_route('subagent.store-link');
    }

    /** Take the subagent's public storefront on/off without touching their portal login. */
    public function toggleStore(Request $request): RedirectResponse
    {
        $subagent = $request->user('subagent');
        $subagent->update(['store_active' => ! $subagent->store_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $subagent->store_active ? 'Your store is now live.' : 'Your store has been deactivated.',
        ]);

        return to_route('subagent.store-link');
    }

    public function generateQr(Request $request): RedirectResponse
    {
        $subagent = $request->user('subagent');
        $this->storeQr($subagent, $this->storeUrl($this->handle($subagent)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'QR code generated.']);

        return to_route('subagent.store-link');
    }

    /** Generate + cache the QR on first request, then return the stored data URI. */
    private function resolveQr(Subagent $subagent, string $url): string
    {
        if ($subagent->referral_qr === null) {
            $this->storeQr($subagent, $url);
        }

        return (string) $subagent->referral_qr;
    }

    private function storeQr(Subagent $subagent, string $url): void
    {
        $subagent->referral_qr = QrCodeGenerator::pngDataUri($url);
        $subagent->save();
    }

    /**
     * The public storefront link. Built from the named route so it resolves correctly in both modes:
     * the subagent_store domain in production, and the /subagent-store path prefix in local dev.
     */
    private function storeUrl(string $handle): string
    {
        return route('subagent.storefront', ['subagentSlug' => $handle]);
    }

    /** The username-derived store handle — never the bare id, so the public link is always /{username}. */
    private function handle(Subagent $subagent): string
    {
        return $subagent->slug ?? $subagent->username ?? (string) $subagent->id;
    }
}
