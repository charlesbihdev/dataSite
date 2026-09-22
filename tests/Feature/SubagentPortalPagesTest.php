<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentPackagePrice;
use App\Models\PricingTier;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubagentPortalPagesTest extends TestCase
{
    use RefreshDatabase;

    private function subagent(array $overrides = []): Subagent
    {
        $tier = PricingTier::create(['name' => 'Std', 'is_active' => true]);
        $agent = Agent::create([
            'pricing_tier_id' => $tier->id, 'name' => 'Cairo Mendez', 'phone' => '0550000001',
            'email' => 'cairo@ex.com', 'username' => 'cairo', 'password' => 'secret', 'is_active' => true,
        ]);

        return Subagent::create(array_merge([
            'agent_id' => $agent->id, 'name' => 'Charles Bih', 'phone' => '0240000001',
            'email' => 'charles@ex.com', 'username' => 'charlesbih', 'slug' => 'charlesbih',
            'password' => 'secret', 'is_active' => true,
        ], $overrides));
    }

    public function test_every_portal_page_renders_for_the_subagent(): void
    {
        $subagent = $this->subagent();

        $pages = [
            'subagent.dashboard' => 'subagent/dashboard',
            'subagent.orders' => 'subagent/orders',
            'subagent.transactions' => 'subagent/transactions',
            'subagent.packages' => 'subagent/packages',
            'subagent.store-link' => 'subagent/store-link',
            'subagent.withdrawals' => 'subagent/withdrawals',
            'subagent.settings' => 'subagent/settings',
        ];

        foreach ($pages as $route => $component) {
            $this->actingAs($subagent, 'subagent')
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_dashboard_shows_the_agent_the_subagent_resells_under(): void
    {
        $subagent = $this->subagent();

        $this->actingAs($subagent, 'subagent')
            ->get(route('subagent.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('subagent/dashboard')
                ->where('greeting.resellerOf', 'Cairo Mendez'));
    }

    public function test_package_price_is_saved_using_the_agents_subagent_price_as_cost(): void
    {
        $subagent = $this->subagent();
        AgentPackagePrice::create([
            'agent_id' => $subagent->agent_id, 'network' => 'mtn', 'capacity_gb' => 5,
            'cost_price' => 3.00, 'selling_price' => 5.00, 'subagent_price' => 4.00, 'is_active' => true,
        ]);

        $this->actingAs($subagent, 'subagent')
            ->post(route('subagent.packages.store'), ['network' => 'mtn', 'capacity_gb' => 5, 'selling_price' => 6.50])
            ->assertRedirect();

        $this->assertDatabaseHas('subagent_package_prices', [
            'subagent_id' => $subagent->id, 'network' => 'mtn', 'capacity_gb' => 5,
            'cost_price' => 4.00, 'selling_price' => 6.50,
        ]);
    }

    public function test_package_not_opened_by_the_agent_cannot_be_priced(): void
    {
        $subagent = $this->subagent();

        // No AgentPackagePrice with a subagent_price exists → nothing to resell.
        $this->actingAs($subagent, 'subagent')
            ->post(route('subagent.packages.store'), ['network' => 'mtn', 'capacity_gb' => 5, 'selling_price' => 6.50])
            ->assertRedirect();

        $this->assertDatabaseCount('subagent_package_prices', 0);
    }

    public function test_profile_update_changes_details_and_stales_the_qr(): void
    {
        $subagent = $this->subagent(['referral_qr' => 'data:image/png;base64,AAAA']);

        $this->actingAs($subagent, 'subagent')
            ->patch(route('subagent.settings.profile'), [
                'name' => 'New Name', 'email' => 'new@ex.com', 'phone' => '0247778888',
                'username' => 'newuser', 'slug' => 'newhandle',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subagents', [
            'id' => $subagent->id, 'name' => 'New Name', 'slug' => 'newhandle', 'referral_qr' => null,
        ]);
    }

    public function test_profile_update_rejects_a_blank_handle(): void
    {
        $subagent = $this->subagent();

        // The handle exists from registration and can only be changed, never cleared.
        $this->actingAs($subagent, 'subagent')
            ->patch(route('subagent.settings.profile'), [
                'name' => 'Charles Bih', 'email' => 'charles@ex.com', 'phone' => '0240000001',
                'username' => 'charlesbih', 'slug' => '',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('subagents', ['id' => $subagent->id, 'slug' => 'charlesbih']);
    }

    public function test_profile_update_rejects_a_handle_taken_by_another_subagent(): void
    {
        $subagent = $this->subagent();
        // Another subagent (under the same agent) already owns "takenhandle".
        Subagent::create([
            'agent_id' => $subagent->agent_id, 'name' => 'Other', 'phone' => '0249999999',
            'email' => 'taken@ex.com', 'username' => 'takenname', 'slug' => 'takenhandle',
            'password' => 'secret', 'is_active' => true,
        ]);

        $this->actingAs($subagent, 'subagent')
            ->patch(route('subagent.settings.profile'), [
                'name' => 'Charles Bih', 'email' => 'charles@ex.com', 'phone' => '0240000001',
                'username' => 'charlesbih', 'slug' => 'takenhandle',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame('charlesbih', $subagent->fresh()->slug);
    }

    public function test_profile_update_rejects_a_handle_with_unpermitted_characters(): void
    {
        $subagent = $this->subagent();

        $this->actingAs($subagent, 'subagent')
            ->patch(route('subagent.settings.profile'), [
                'name' => 'Charles Bih', 'email' => 'charles@ex.com', 'phone' => '0240000001',
                'username' => 'charlesbih', 'slug' => 'my store!', // space + symbol not allowed
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame('charlesbih', $subagent->fresh()->slug);
    }

    public function test_withdrawal_exceeding_available_balance_is_rejected(): void
    {
        $subagent = $this->subagent(); // no credited earnings → balance is 0

        $this->actingAs($subagent, 'subagent')
            ->post(route('subagent.withdrawals.store'), ['method' => 'momo', 'amount' => 50, 'destination' => '0247778888'])
            ->assertRedirect();

        $this->assertDatabaseCount('withdrawals', 0);
    }

    public function test_toggling_the_store_flips_store_active(): void
    {
        $subagent = $this->subagent(['store_active' => true]);

        $this->actingAs($subagent, 'subagent')
            ->post(route('subagent.store-link.toggle'))
            ->assertRedirect();

        $this->assertFalse($subagent->refresh()->store_active);
    }
}
