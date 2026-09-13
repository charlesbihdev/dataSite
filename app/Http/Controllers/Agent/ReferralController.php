<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateReferralContactRequest;
use App\Models\Agent;
use App\Models\AgentPackagePrice;
use App\Models\Order;
use App\Support\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My Referral Link: the agent's customer-facing storefront link, its performance (clicks/sales),
 * the contact details shown on the referral checkout, and the active packages customers will see.
 * Packages mirror the agent's active Package Pricing rows, so the link updates as they change.
 */
class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $agent = $request->user();
        $referralUrl = $this->referralUrl($agent->slug ?? (string) $agent->id);

        $storefront = $agent->orders()->where('source', Order::SOURCE_STOREFRONT);
        $clicks = (int) $agent->referral_clicks;
        $sales = (clone $storefront)->count();
        $revenue = (float) (clone $storefront)->where('payment_status', Order::PAYMENT_PAID)->sum('customer_price');

        return Inertia::render('agent/referral', [
            'referralUrl' => $referralUrl,
            // Deferred: generating the QR on first load is the slow part, so the page renders
            // immediately and the QR streams in (behind a skeleton). Cached thereafter.
            'referralQr' => Inertia::defer(fn () => $this->resolveQr($agent, $referralUrl)),
            'contact' => [
                'store_name' => $agent->store_name,
                'whatsapp_number' => $agent->whatsapp_number,
                'whatsapp_group_link' => $agent->whatsapp_group_link,
            ],
            'stats' => [
                'clicks' => $clicks,
                'sales' => $sales,
                'revenue' => $revenue,
                'activePackages' => $agent->packagePrices()->where('is_active', true)->count(),
                'conversion' => $clicks > 0 ? round($sales / $clicks * 100, 1) : 0.0,
            ],
            'packages' => $agent->packagePrices()->where('is_active', true)->latest('id')->get()->map(fn (AgentPackagePrice $p): array => [
                'id' => $p->id,
                'network' => $p->network,
                'capacityGb' => $p->capacity_gb,
                'price' => (float) $p->selling_price,
                'profit' => round((float) $p->selling_price - (float) $p->cost_price, 2),
            ]),
        ]);
    }

    public function updateContact(UpdateReferralContactRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Referral contact details saved.']);

        return to_route('agent.referral');
    }

    public function generateQr(Request $request): RedirectResponse
    {
        $agent = $request->user();
        $this->storeQr($agent, $this->referralUrl($agent->slug ?? (string) $agent->id));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'QR code generated.']);

        return to_route('agent.referral');
    }

    /** Generate + cache the QR on first request, then return the stored data URI. */
    private function resolveQr(Agent $agent, string $url): string
    {
        if ($agent->referral_qr === null) {
            $this->storeQr($agent, $url);
        }

        return (string) $agent->referral_qr;
    }

    private function storeQr(Agent $agent, string $url): void
    {
        $agent->referral_qr = QrCodeGenerator::pngDataUri($url);
        $agent->save();
    }

    private function referralUrl(string $handle): string
    {
        $domain = config('surfaces.agent_store');

        return $domain ? "https://{$domain}/buy/{$handle}" : url("/buy/{$handle}");
    }
}
