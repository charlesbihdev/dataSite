<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BaseCostRequest;
use App\Http\Requests\Admin\TierPriceRequest;
use App\Models\BaseCost;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Http\RedirectResponse;
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
        return Inertia::render('admin/pricing', [
            'baseCosts' => BaseCost::query()->orderBy('network')->orderBy('min_gb')->get()->map($this->mapBaseCost(...)),
            'tiers' => PricingTier::query()->with(['prices' => fn ($q) => $q->orderBy('network')->orderBy('min_gb')])
                ->orderBy('name')->get()->map(fn (PricingTier $tier): array => [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'isActive' => $tier->is_active,
                    'prices' => $tier->prices->map($this->mapTierPrice(...))->all(),
                ]),
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
