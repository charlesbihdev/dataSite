<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ApiKey;
use App\Models\PricingTier;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    private PricingTier $tier;

    private Agent $agent;

    private Subagent $subagent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = PricingTier::create(['name' => 'Gold Tier', 'is_active' => true]);
        $this->agent = Agent::create([
            'pricing_tier_id' => $this->tier->id,
            'name' => 'Test Agent',
            'phone' => '+233240000001',
            'email' => 'agent@test.com',
            'username' => 'testagent',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->subagent = Subagent::create([
            'agent_id' => $this->agent->id,
            'name' => 'Test Subagent',
            'phone' => '+233240000002',
            'email' => 'subagent@test.com',
            'username' => 'testsubagent',
            'password' => 'secret123',
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_mint_api_key_for_agent_with_one_time_raw_reveal(): void
    {
        $response = $this->post(route('admin.accounts.api-keys.store', [
            'type' => 'agents',
            'id' => $this->agent->id,
        ]), [
            'name' => 'Production Key',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('rawApiKey');

        $flash = session('rawApiKey');
        $this->assertIsArray($flash);
        $this->assertSame('Production Key', $flash['name']);
        $this->assertSame('Test Agent', $flash['accountName']);
        $this->assertStringStartsWith('dsk_', $flash['rawKey']);

        $key = ApiKey::where('owner_type', $this->agent->getMorphClass())
            ->where('owner_id', $this->agent->id)
            ->first();

        $this->assertNotNull($key);
        $this->assertSame('Production Key', $key->name);
        $this->assertSame(ApiKey::hash($flash['rawKey']), $key->key_hash);
        $this->assertTrue($key->is_active);
    }

    public function test_superadmin_can_mint_api_key_for_subagent(): void
    {
        $response = $this->post(route('admin.accounts.api-keys.store', [
            'type' => 'subagents',
            'id' => $this->subagent->id,
        ]), [
            'name' => 'Subagent Integration Key',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('rawApiKey');

        $this->assertDatabaseHas('api_keys', [
            'owner_type' => $this->subagent->getMorphClass(),
            'owner_id' => $this->subagent->id,
            'name' => 'Subagent Integration Key',
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_toggle_api_key_status(): void
    {
        [$key] = ApiKey::generate($this->agent, 'Toggle Key');
        $this->assertTrue($key->is_active);

        $response = $this->post(route('admin.api-keys.toggle', $key));
        $response->assertRedirect();

        $this->assertFalse($key->fresh()->is_active);

        $this->post(route('admin.api-keys.toggle', $key));
        $this->assertTrue($key->fresh()->is_active);
    }

    public function test_superadmin_can_revoke_api_key(): void
    {
        [$key] = ApiKey::generate($this->agent, 'Key To Delete');

        $response = $this->delete(route('admin.api-keys.destroy', $key));
        $response->assertRedirect();

        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
    }
}
