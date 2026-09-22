<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BaseCostRequest;
use App\Http\Requests\Admin\TierPriceRequest;
use App\Models\BaseCost;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One screen for the full pricing economics: base cost (what we pay Databundleshub) alongside
 * each tier's selling rate. The cost-floor guardrail lives in TierPriceRequest.
 */
class PricingController extends Controller
{
    public function index(): Response
    {
        $tiers = PricingTier::query()->withCount('agents')->with(['prices' => fn ($q) => $q->orderBy('network')->orderBy('min_gb')])
            ->orderBy('name')->get();

        return Inertia::render('admin/pricing', [
            'baseCosts' => BaseCost::query()->orderBy('network')->orderBy('min_gb')->get()->map($this->mapBaseCost(...)),
            'tiers' => $tiers->map(fn (PricingTier $tier): array => [
                'id' => $tier->id,
                'name' => $tier->name,
                'isActive' => $tier->is_active,
                'agentsCount' => $tier->agents_count,
                'prices' => $tier->prices->map($this->mapTierPrice(...))->all(),
            ]),
            'tierList' => $tiers->map(fn (PricingTier $tier): array => [
                'id' => $tier->id,
                'name' => $tier->name,
                'isActive' => $tier->is_active,
                'isDefault' => $tier->is_default,
                'isUndeletable' => $tier->is_undeletable,
                'agentsCount' => $tier->agents_count,
            ])->all(),
        ]);
    }

    public function storeBaseCost(BaseCostRequest $request): RedirectResponse
    {
        BaseCost::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Base cost band added.']);

        return back();
    }

    public function updateBaseCost(BaseCostRequest $request, BaseCost $baseCost): RedirectResponse
    {
        $baseCost->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Base cost band updated.']);

        return back();
    }

    public function destroyBaseCost(BaseCost $baseCost): RedirectResponse
    {
        $baseCost->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Base cost band removed.']);

        return back();
    }

    public function storeTierPrice(TierPriceRequest $request): RedirectResponse
    {
        TierPrice::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Selling rate added.']);

        return back();
    }

    public function updateTierPrice(TierPriceRequest $request, TierPrice $tierPrice): RedirectResponse
    {
        $tierPrice->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Selling rate updated.']);

        return back();
    }

    public function destroyTierPrice(TierPrice $tierPrice): RedirectResponse
    {
        $tierPrice->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Selling rate removed.']);

        return back();
    }

    public function storeTier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:pricing_tiers,name'],
            'is_active' => ['boolean'],
        ]);

        PricingTier::create($validated);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pricing tier created.']);

        return back();
    }

    public function updateTier(Request $request, PricingTier $pricingTier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('pricing_tiers', 'name')->ignore($pricingTier->id)],
            'is_active' => ['boolean'],
        ]);

        $pricingTier->update($validated);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pricing tier updated.']);

        return back();
    }

    public function destroyTier(PricingTier $pricingTier): RedirectResponse
    {
        if ($pricingTier->is_undeletable) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'System tiers cannot be deleted.']);

            return back();
        }

        if ($pricingTier->agents()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Reassign agents before deleting this tier.']);

            return back();
        }

        $pricingTier->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pricing tier deleted.']);

        return back();
    }

    public function cloneNetwork(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_network' => ['required', 'string', Rule::in(['telecel', 'at'])],
        ]);

        $target = $validated['target_network'];

        BaseCost::where('network', $target)->delete();
        TierPrice::where('network', $target)->delete();

        $mtnBase = BaseCost::where('network', 'mtn')->get();
        $mtnTier = TierPrice::where('network', 'mtn')->get();

        foreach ($mtnBase as $base) {
            $new = $base->replicate();
            $new->network = $target;
            $new->save();
        }

        foreach ($mtnTier as $tier) {
            $new = $tier->replicate();
            $new->network = $target;
            $new->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($target).' pricing cloned from MTN.']);

        return back();
    }

    public function resetNetwork(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_network' => ['required', 'string', Rule::in(['telecel', 'at'])],
        ]);

        $target = $validated['target_network'];

        BaseCost::where('network', $target)->delete();
        TierPrice::where('network', $target)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($target).' pricing reset.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBaseCost(BaseCost $cost): array
    {
        return [
            'id' => $cost->id,
            'network' => $cost->network,
            'minGb' => (float) $cost->min_gb,
            'maxGb' => (float) $cost->max_gb,
            'costPerGb' => (float) $cost->cost_per_gb,
            'isActive' => $cost->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTierPrice(TierPrice $price): array
    {
        return [
            'id' => $price->id,
            'tierId' => $price->pricing_tier_id,
            'network' => $price->network,
            'minGb' => (float) $price->min_gb,
            'maxGb' => (float) $price->max_gb,
            'pricePerGb' => (float) $price->price_per_gb,
            'isActive' => $price->is_active,
        ];
    }
}
