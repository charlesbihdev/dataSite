<?php

namespace Tests\Feature;

use App\Models\BaseCost;
use App\Models\PricingTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_base_cost_band_can_be_created(): void
    {
        $this->post('/admin/pricing/base-costs', [
            'network' => 'mtn', 'min_gb' => 1, 'max_gb' => 10, 'cost_per_gb' => 4, 'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('base_costs', ['network' => 'mtn', 'cost_per_gb' => 4]);
    }

    public function test_tier_price_below_base_cost_is_rejected(): void
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 10, 'cost_per_gb' => 5, 'is_active' => true]);

        $this->from('/admin/pricing')
            ->post('/admin/pricing/tier-prices', [
                'pricing_tier_id' => $tier->id, 'network' => 'mtn',
                'min_gb' => 1, 'max_gb' => 10, 'price_per_gb' => 4, 'is_active' => true,
            ])
            ->assertRedirect('/admin/pricing')
            ->assertSessionHasErrors('price_per_gb');

        $this->assertDatabaseCount('tier_prices', 0);
    }

    public function test_tier_price_at_or_above_base_cost_is_accepted(): void
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 10, 'cost_per_gb' => 5, 'is_active' => true]);

        $this->post('/admin/pricing/tier-prices', [
            'pricing_tier_id' => $tier->id, 'network' => 'mtn',
            'min_gb' => 1, 'max_gb' => 10, 'price_per_gb' => 6, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tier_prices', ['pricing_tier_id' => $tier->id, 'price_per_gb' => 6]);
    }

    public function test_tier_price_allowed_when_no_base_cost_exists(): void
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);

        $this->post('/admin/pricing/tier-prices', [
            'pricing_tier_id' => $tier->id, 'network' => 'telecel',
            'min_gb' => 1, 'max_gb' => 5, 'price_per_gb' => 3, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tier_prices', 1);
    }
}
