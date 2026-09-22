<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StorePackagePriceRequest;
use App\Models\AgentPackagePrice;
use App\Services\Pricing\PriceQuote;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Package Pricing page: set a selling (and optional sub-agent) price per data package.
 * Cost is the agent's tier rate, resolved live via PriceQuote. This is a MANAGEMENT record — it does
 * not yet drive live checkout pricing, which stays on the tier cascade (ARCHITECTURE §4).
 */
class PackagesController extends Controller
{
    /** Discrete package sizes offered for pricing, filtered to what the agent has a tier rate for. */
    private const CANDIDATE_SIZES = [1, 2, 3, 4, 5, 6, 8, 10, 15, 20, 25, 30, 40, 50, 100];

    public function index(Request $request, PriceQuote $quote): Response
    {
        $agent = $request->user();

        $packages = $agent->packagePrices()->orderByNetwork()->paginate(30)->through(function (AgentPackagePrice $p): array {
            $cost = (float) $p->cost_price;
            $selling = (float) $p->selling_price;

            return [
                'id' => $p->id,
                'network' => $p->network,
                'capacityGb' => $p->capacity_gb,
                'cost' => $cost,
                'selling' => $selling,
                'subagent' => $p->subagent_price !== null ? (float) $p->subagent_price : null,
                'profit' => round($selling - $cost, 2),
                'margin' => $selling > 0 ? round(($selling - $cost) / $selling * 100, 1) : 0.0,
                'isActive' => $p->is_active,
            ];
        });

        // Stats aggregate over ALL rows (not just the current page).
        $active = $agent->packagePrices()->where('is_active', true);

        return Inertia::render('agent/packages', [
            'packages' => $packages,
            'options' => $this->availablePackages($agent, $quote),
            'stats' => [
                'total' => $agent->packagePrices()->count(),
                'active' => (clone $active)->count(),
                'avgMargin' => round((float) (clone $active)->selectRaw('AVG((selling_price - cost_price) / selling_price * 100) as m')->value('m'), 1),
                'potentialProfit' => round((float) (clone $active)->selectRaw('SUM(selling_price - cost_price) as p')->value('p'), 2),
            ],
        ]);
    }

    public function store(StorePackagePriceRequest $request, PriceQuote $quote): RedirectResponse
    {
        $agent = $request->user();
        $network = $request->string('network')->value();
        $capacity = (int) $request->integer('capacity_gb');
        $selling = round((float) $request->input('selling_price'), 2);
        $subagent = $request->input('subagent_price') !== null ? round((float) $request->input('subagent_price'), 2) : null;

        $price = $quote->for($agent, $network, $capacity);
        if ($price === null) {
            return $this->toast('error', 'No cost price is configured for this package.');
        }
        $cost = $price['amount'];

        if ($selling < $cost) {
            return $this->toast('error', 'Selling price must be at least your cost price (' . number_format($cost, 2) . ').');
        }
        if ($subagent !== null && $subagent < $cost) {
            return $this->toast('error', 'Sub-agent price must be at least your cost price.');
        }

        $agent->packagePrices()->updateOrCreate(
            ['network' => $network, 'capacity_gb' => $capacity],
            ['cost_price' => $cost, 'selling_price' => $selling, 'subagent_price' => $subagent, 'is_active' => true],
        );

        return $this->toast('success', 'Package price saved.');
    }

    public function toggle(Request $request, AgentPackagePrice $package): RedirectResponse
    {
        $this->authorizeOwner($request, $package);
        $package->update(['is_active' => ! $package->is_active]);

        return $this->toast('success', $package->is_active ? 'Package activated.' : 'Package deactivated.');
    }

    public function destroy(Request $request, AgentPackagePrice $package): RedirectResponse
    {
        $this->authorizeOwner($request, $package);
        $package->delete();

        return $this->toast('success', 'Package removed.');
    }

    /**
     * Packages the agent can price: each candidate size that resolves to a tier cost, with that cost.
     *
     * @return list<array{value: string, network: string, capacityGb: int, label: string, cost: float}>
     */
    private function availablePackages($agent, PriceQuote $quote): array
    {
        $options = [];
        foreach ([GhanaMobileNetwork::MTN, GhanaMobileNetwork::TELECEL, GhanaMobileNetwork::AT] as $network) {
            foreach (self::CANDIDATE_SIZES as $size) {
                $price = $quote->for($agent, $network, $size);
                if ($price === null) {
                    continue;
                }
                $options[] = [
                    'value' => "{$network}:{$size}",
                    'network' => $network,
                    'capacityGb' => $size,
                    'label' => strtoupper($network) . " {$size}GB",
                    'cost' => $price['amount'],
                ];
            }
        }

        return $options;
    }

    private function authorizeOwner(Request $request, AgentPackagePrice $package): void
    {
        abort_unless($package->agent_id === $request->user()->getKey(), 403);
    }

    private function toast(string $level, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $level, 'message' => $message]);

        return to_route('agent.packages');
    }
}
