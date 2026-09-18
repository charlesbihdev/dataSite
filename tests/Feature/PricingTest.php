<?php

namespace Tests\Feature;

use App\Models\BaseCost;
use App\Models\PricingTier;
use App\Models\TierPrice;
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

    public function test_overlapping_tier_price_band_is_rejected(): void
    {
        $tier = $this->tierWithBand(1, 10);

        $this->from('/admin/pricing')
            ->post('/admin/pricing/tier-prices', [
                'pricing_tier_id' => $tier->id, 'network' => 'telecel',
                'min_gb' => 5, 'max_gb' => 20, 'price_per_gb' => 3, 'is_active' => true,
            ])
            ->assertRedirect('/admin/pricing')
            ->assertSessionHasErrors('max_gb');

        $this->assertDatabaseCount('tier_prices', 1);
    }

    public function test_tier_price_band_sharing_an_endpoint_is_rejected(): void
    {
        $tier = $this->tierWithBand(1, 10);

        $this->from('/admin/pricing')
            ->post('/admin/pricing/tier-prices', [
                'pricing_tier_id' => $tier->id, 'network' => 'telecel',
                'min_gb' => 10, 'max_gb' => 20, 'price_per_gb' => 3, 'is_active' => true,
            ])
            ->assertSessionHasErrors('max_gb');

        $this->assertDatabaseCount('tier_prices', 1);
    }

    public function test_contiguous_tier_price_band_is_accepted(): void
    {
        $tier = $this->tierWithBand(1, 10);

        $this->post('/admin/pricing/tier-prices', [
            'pricing_tier_id' => $tier->id, 'network' => 'telecel',
            'min_gb' => 11, 'max_gb' => 20, 'price_per_gb' => 3, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tier_prices', 2);
    }

    public function test_editing_a_band_does_not_collide_with_itself(): void
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        $price = TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'telecel',
            'min_gb' => 1, 'max_gb' => 10, 'price_per_gb' => 3, 'is_active' => true,
        ]);

        $this->put("/admin/pricing/tier-prices/{$price->id}", [
            'pricing_tier_id' => $tier->id, 'network' => 'telecel',
            'min_gb' => 1, 'max_gb' => 10, 'price_per_gb' => 5, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tier_prices', ['id' => $price->id, 'price_per_gb' => 5]);
    }

    public function test_inactive_band_does_not_reserve_its_range(): void
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'telecel',
            'min_gb' => 1, 'max_gb' => 10, 'price_per_gb' => 3, 'is_active' => false,
        ]);

        $this->post('/admin/pricing/tier-prices', [
            'pricing_tier_id' => $tier->id, 'network' => 'telecel',
            'min_gb' => 5, 'max_gb' => 20, 'price_per_gb' => 3, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tier_prices', 2);
    }

    public function test_overlap_is_scoped_to_the_network(): void
    {
        $tier = $this->tierWithBand(1, 10);

        $this->post('/admin/pricing/tier-prices', [
            'pricing_tier_id' => $tier->id, 'network' => 'at',
            'min_gb' => 1, 'max_gb' => 10, 'price_per_gb' => 3, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tier_prices', 2);
    }

    public function test_overlapping_base_cost_band_is_rejected(): void
    {
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 10, 'cost_per_gb' => 4, 'is_active' => true]);

        $this->from('/admin/pricing')
            ->post('/admin/pricing/base-costs', [
                'network' => 'mtn', 'min_gb' => 5, 'max_gb' => 20, 'cost_per_gb' => 4, 'is_active' => true,
            ])
            ->assertSessionHasErrors('max_gb');

        $this->assertDatabaseCount('base_costs', 1);
    }

    private function tierWithBand(float $minGb, float $maxGb): PricingTier
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'telecel',
            'min_gb' => $minGb, 'max_gb' => $maxGb, 'price_per_gb' => 3, 'is_active' => true,
        ]);

        return $tier;
    }
}
