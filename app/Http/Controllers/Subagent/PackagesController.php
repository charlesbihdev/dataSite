<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subagent\StorePackagePriceRequest;
use App\Models\AgentPackagePrice;
use App\Models\Subagent;
use App\Models\SubagentPackagePrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's Package Pricing page — mirrors the agent's, one rung down. The subagent sets a
 * selling price per package; their COST is the price their agent opened to sub-agents
 * (AgentPackagePrice::subagent_price), frozen at save time. No onward sub-agent price (bottom rung).
 */
class PackagesController extends Controller
{
    public function index(Request $request): Response
    {
        $subagent = $request->user('subagent');

        $packages = $subagent->packagePrices()->orderByNetwork()->paginate(30)->through(function (SubagentPackagePrice $p): array {
            $cost = (float) $p->cost_price;
            $selling = (float) $p->selling_price;

            return [
                'id' => $p->id,
                'network' => $p->network,
                'capacityGb' => $p->capacity_gb,
                'cost' => $cost,
                'selling' => $selling,
                'profit' => round($selling - $cost, 2),
                'margin' => $selling > 0 ? round(($selling - $cost) / $selling * 100, 1) : 0.0,
                'isActive' => $p->is_active,
            ];
        });

        $active = $subagent->packagePrices()->where('is_active', true);

        return Inertia::render('subagent/packages', [
            'packages' => $packages,
            'options' => $this->availablePackages($subagent),
            'stats' => [
                'total' => $subagent->packagePrices()->count(),
                'active' => (clone $active)->count(),
                'avgMargin' => round((float) (clone $active)->selectRaw('AVG((selling_price - cost_price) / selling_price * 100) as m')->value('m'), 1),
                'potentialProfit' => round((float) (clone $active)->selectRaw('SUM(selling_price - cost_price) as p')->value('p'), 2),
            ],
        ]);
    }

    public function store(StorePackagePriceRequest $request): RedirectResponse
    {
        $subagent = $request->user('subagent');
        $network = $request->string('network')->value();
        $capacity = (int) $request->integer('capacity_gb');
        $selling = round((float) $request->input('selling_price'), 2);

        $cost = $this->costFor($subagent, $network, $capacity);
        if ($cost === null) {
            return $this->toast('error', 'This package is not available from your agent.');
        }

        if ($selling < $cost) {
            return $this->toast('error', 'Selling price must be at least your cost price (' . number_format($cost, 2) . ').');
        }

        $subagent->packagePrices()->updateOrCreate(
            ['network' => $network, 'capacity_gb' => $capacity],
            ['cost_price' => $cost, 'selling_price' => $selling, 'is_active' => true],
        );

        return $this->toast('success', 'Package price saved.');
    }

    public function toggle(Request $request, SubagentPackagePrice $package): RedirectResponse
    {
        $this->authorizeOwner($request, $package);
        $package->update(['is_active' => ! $package->is_active]);

        return $this->toast('success', $package->is_active ? 'Package activated.' : 'Package deactivated.');
    }

    public function destroy(Request $request, SubagentPackagePrice $package): RedirectResponse
    {
        $this->authorizeOwner($request, $package);
        $package->delete();

        return $this->toast('success', 'Package removed.');
    }

    /**
     * The cost a subagent pays for a package = the price their agent opened to sub-agents on an active
     * package. Null when the agent hasn't set a sub-agent price for it.
     */
    private function costFor(Subagent $subagent, string $network, int $capacity): ?float
    {
        $price = AgentPackagePrice::query()
            ->where('agent_id', $subagent->agent_id)
            ->where('network', $network)
            ->where('capacity_gb', $capacity)
            ->where('is_active', true)
            ->whereNotNull('subagent_price')
            ->value('subagent_price');

        return $price !== null ? (float) $price : null;
    }

    /**
     * Packages the subagent can price: every package their agent has opened to sub-agents (active,
     * with a sub-agent price), using that sub-agent price as the subagent's cost.
     *
     * @return list<array{value: string, network: string, capacityGb: int, label: string, cost: float}>
     */
    private function availablePackages(Subagent $subagent): array
    {
        return AgentPackagePrice::query()
            ->where('agent_id', $subagent->agent_id)
            ->where('is_active', true)
            ->whereNotNull('subagent_price')
            ->orderByNetwork()
            ->get()
            ->map(fn(AgentPackagePrice $p): array => [
                'value' => "{$p->network}:{$p->capacity_gb}",
                'network' => $p->network,
                'capacityGb' => (int) $p->capacity_gb,
                'label' => strtoupper($p->network) . " {$p->capacity_gb}GB",
                'cost' => (float) $p->subagent_price,
            ])
            ->all();
    }

    private function authorizeOwner(Request $request, SubagentPackagePrice $package): void
    {
        abort_unless($package->subagent_id === $request->user('subagent')->getKey(), 403);
    }

    private function toast(string $level, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $level, 'message' => $message]);

        return to_route('subagent.packages');
    }
}
