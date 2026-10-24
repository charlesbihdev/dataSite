<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentPackagePrice;
use App\Models\BaseCost;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentPackagesTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create(['pricing_tier_id' => $tier->id, 'network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 5.0, 'is_active' => true]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'cost_per_gb' => 3.0, 'is_active' => true]);

        $this->agent = Agent::factory()->create(['pricing_tier_id' => $tier->id]);
        $this->actingAs($this->agent, 'agent');
    }

    public function test_page_offers_priceable_packages(): void
    {
        $this->get(route('agent.packages'))->assertInertia(
            fn (Assert $page) => $page
                ->component('agent/packages')
                ->where('options.0.network', 'mtn')
                ->where('options.0.cost', fn ($c) => $c > 0)
        );
    }

    public function test_saving_a_package_freezes_cost_and_computes_profit(): void
    {
        $this->post(route('agent.packages.store'), ['network' => 'mtn', 'capacity_gb' => 5, 'selling_price' => 30, 'subagent_price' => 28])
            ->assertRedirect(route('agent.packages'));

        $p = AgentPackagePrice::sole();
        $this->assertSame('25.00', $p->cost_price);   // 5 GB * 5.00/GB
        $this->assertSame('30.00', $p->selling_price);
        $this->assertSame('28.00', $p->subagent_price);
        $this->assertTrue($p->is_active);
    }

    public function test_selling_below_cost_is_rejected(): void
    {
        $this->post(route('agent.packages.store'), ['network' => 'mtn', 'capacity_gb' => 5, 'selling_price' => 10]);

        $this->assertSame(0, AgentPackagePrice::count());
    }

    public function test_toggle_and_delete(): void
    {
        $p = $this->agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 5, 'cost_price' => 25, 'selling_price' => 30, 'is_active' => true]);

        $this->post(route('agent.packages.toggle', $p));
        $this->assertFalse($p->fresh()->is_active);

        $this->delete(route('agent.packages.destroy', $p));
        $this->assertSame(0, AgentPackagePrice::count());
    }

    public function test_telecel_package_below_10gb_is_rejected_but_mtn_small_sizes_are_fine(): void
    {
        TierPrice::create(['pricing_tier_id' => $this->agent->pricing_tier_id, 'network' => 'telecel', 'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 5.0, 'is_active' => true]);

        $this->post(route('agent.packages.store'), ['network' => 'telecel', 'capacity_gb' => 5, 'selling_price' => 40])
            ->assertSessionHasErrors('capacity_gb');
        $this->post(route('agent.packages.store'), ['network' => 'mtn', 'capacity_gb' => 1, 'selling_price' => 8])
            ->assertSessionHasNoErrors();

        $this->assertSame(['mtn'], AgentPackagePrice::pluck('network')->all());
    }

    public function test_dashboard_warns_about_existing_telecel_packages_below_10gb(): void
    {
        $this->agent->packagePrices()->create(['network' => 'telecel', 'capacity_gb' => 1, 'cost_price' => 5, 'selling_price' => 7, 'is_active' => true]);
        $this->agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 1, 'cost_price' => 5, 'selling_price' => 7, 'is_active' => true]);

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->has('unsellablePackages', 1)
                ->where('unsellablePackages.0.network', 'telecel')
                ->where('unsellablePackages.0.minGb', 10)
        );
    }
}
