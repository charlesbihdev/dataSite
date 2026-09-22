<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ApiKey;
use App\Models\PricingTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        $this->agent = Agent::factory()->create(['pricing_tier_id' => $tier->id]);
        $this->actingAs($this->agent, 'agent');
    }

    public function test_agent_can_view_api_keys_page(): void
    {
        ApiKey::generate($this->agent, 'Existing Key');

        $this->get(route('agent.api-keys'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('agent/api-keys')
                    ->has('keys', 1)
                    ->where('keys.0.name', 'Existing Key')
                    ->where('stats.total', 1)
                    ->where('stats.active', 1)
                    ->has('baseUrl')
            );
    }

    public function test_agent_can_mint_api_key_with_one_time_raw_reveal(): void
    {
        $response = $this->post(route('agent.api-keys.store'), [
            'name' => 'Mobile App Integration',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('rawApiKey');

        $flash = session('rawApiKey');
        $this->assertIsArray($flash);
        $this->assertSame('Mobile App Integration', $flash['name']);
        $this->assertStringStartsWith('dsk_', $flash['rawKey']);

        $key = ApiKey::where('owner_type', $this->agent->getMorphClass())
            ->where('owner_id', $this->agent->id)
            ->first();

        $this->assertNotNull($key);
        $this->assertSame('Mobile App Integration', $key->name);
        $this->assertSame(ApiKey::hash($flash['rawKey']), $key->key_hash);
        $this->assertTrue($key->is_active);
    }

    public function test_agent_can_toggle_api_key_status(): void
    {
        [$key] = ApiKey::generate($this->agent, 'Toggle Key');
        $this->assertTrue($key->is_active);

        $response = $this->post(route('agent.api-keys.toggle', $key));
        $response->assertRedirect();
        $this->assertFalse($key->fresh()->is_active);

        $response = $this->post(route('agent.api-keys.toggle', $key));
        $response->assertRedirect();
        $this->assertTrue($key->fresh()->is_active);
    }

    public function test_agent_can_revoke_api_key(): void
    {
        [$key] = ApiKey::generate($this->agent, 'Revoke Key');

        $response = $this->delete(route('agent.api-keys.destroy', $key));
        $response->assertRedirect();

        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
    }

    public function test_agent_cannot_manage_another_agents_api_key(): void
    {
        $otherAgent = Agent::factory()->create();
        [$key] = ApiKey::generate($otherAgent, 'Other Key');

        $this->post(route('agent.api-keys.toggle', $key))->assertForbidden();
        $this->delete(route('agent.api-keys.destroy', $key))->assertForbidden();
    }

    public function test_guest_cannot_access_agent_api_keys(): void
    {
        auth('agent')->logout();

        $this->get(route('agent.api-keys'))
            ->assertRedirect(route('login'));
    }

    public function test_agent_can_view_api_documentation_page(): void
    {
        $this->get(route('agent.api-documentation'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('agent/api-documentation')
                    ->has('baseUrl')
                    ->has('catalog')
                    ->where('role', 'agent')
            );
    }

    public function test_agent_cannot_exceed_three_active_api_keys(): void
    {
        ApiKey::generate($this->agent, 'Key 1');
        ApiKey::generate($this->agent, 'Key 2');
        ApiKey::generate($this->agent, 'Key 3');

        $response = $this->post(route('agent.api-keys.store'), [
            'name' => 'Key 4',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('api_keys', ['name' => 'Key 4']);
    }

    public function test_agent_cannot_activate_fourth_key_if_three_are_already_active(): void
    {
        ApiKey::generate($this->agent, 'Key 1');
        ApiKey::generate($this->agent, 'Key 2');
        ApiKey::generate($this->agent, 'Key 3');

        [$key4] = ApiKey::generate($this->agent, 'Key 4');
        $key4->is_active = false;
        $key4->save();

        // Attempt to toggle key4 to active while 3 are already active
        $response = $this->post(route('agent.api-keys.toggle', $key4));
        $response->assertRedirect();

        $this->assertFalse($key4->fresh()->is_active);
    }
}
