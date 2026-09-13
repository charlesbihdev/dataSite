<?php

namespace Tests\Feature;

use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_link_stats_and_active_packages(): void
    {
        $agent = Agent::factory()->create(['slug' => 'kofi', 'referral_clicks' => 10]);
        $agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 1, 'cost_price' => 4, 'selling_price' => 4.30, 'is_active' => true]);
        $agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 2, 'cost_price' => 8, 'selling_price' => 9, 'is_active' => false]);

        // The QR is a deferred prop, so it is NOT in the initial response — the page renders fast.
        $this->actingAs($agent, 'agent')->get(route('agent.referral'))->assertInertia(
            fn (Assert $page) => $page
                ->component('agent/referral')
                ->where('referralUrl', fn ($u) => str_contains($u, '/buy/kofi'))
                ->missing('referralQr')
                ->where('stats.clicks', 10)
                ->where('stats.activePackages', 1)
                ->has('packages', 1)
                ->where('packages.0.profit', 0.3)
        );
    }

    public function test_qr_can_be_regenerated(): void
    {
        $agent = Agent::factory()->create(['referral_qr' => 'stale']);

        $this->actingAs($agent, 'agent')->post(route('agent.referral.qr'))->assertRedirect(route('agent.referral'));

        $this->assertStringContainsString('data:image/png', (string) $agent->fresh()->referral_qr);
    }

    public function test_contact_details_can_be_saved(): void
    {
        $agent = Agent::factory()->create();

        $this->actingAs($agent, 'agent')->put(route('agent.referral.contact'), [
            'store_name' => 'Kofi Data Store',
            'whatsapp_number' => '0548715098',
            'whatsapp_group_link' => 'https://chat.whatsapp.com/abc',
        ])->assertRedirect(route('agent.referral'));

        $agent->refresh();
        $this->assertSame('Kofi Data Store', $agent->store_name);
        $this->assertSame('0548715098', $agent->whatsapp_number);
    }
}
