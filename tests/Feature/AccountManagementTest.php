<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Order;
use App\Models\PricingTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function agent(string $phone = '0551000001'): Agent
    {
        return Agent::create(['name' => 'Kofi', 'phone' => $phone, 'password' => 'secret-1234', 'is_active' => true]);
    }

    public function test_admin_can_create_an_agent_with_opening_balance(): void
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);

        $this->post('/admin/accounts/agents', [
            'name' => 'Ama Owusu',
            'phone' => '0551000009',
            'password' => 'Str0ng-Pass!',
            'pricing_tier_id' => $tier->id,
            'initial_balance' => 50,
        ])->assertRedirect('/admin/accounts?type=agents')->assertSessionHasNoErrors();

        $agent = Agent::where('phone', '0551000009')->first();
        $this->assertNotNull($agent);
        $this->assertTrue(Hash::check('Str0ng-Pass!', $agent->password));
        $this->assertSame(50.0, (float) $agent->walletOrCreate()->balance);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        $this->agent();

        $this->from('/admin/accounts')
            ->post('/admin/accounts/agents', [
                'name' => 'Clash',
                'phone' => '0551000001',
                'password' => 'Str0ng-Pass!',
                'pricing_tier_id' => $tier->id,
            ])
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('agents', 1);
    }

    public function test_add_funds_credits_then_deducts(): void
    {
        $agent = $this->agent();

        $this->post("/admin/accounts/agents/{$agent->id}/funds", ['amount' => 100])->assertRedirect();
        $this->assertSame(100.0, (float) $agent->walletOrCreate()->fresh()->balance);

        $this->post("/admin/accounts/agents/{$agent->id}/funds", ['amount' => -30])->assertRedirect();
        $this->assertSame(70.0, (float) $agent->walletOrCreate()->fresh()->balance);
    }

    public function test_deducting_more_than_balance_is_rejected(): void
    {
        $agent = $this->agent();
        $agent->walletOrCreate()->credit(20, 'topup');

        $this->post("/admin/accounts/agents/{$agent->id}/funds", ['amount' => -50])->assertRedirect();

        $this->assertSame(20.0, (float) $agent->walletOrCreate()->fresh()->balance);
    }

    public function test_reset_password_changes_the_stored_hash(): void
    {
        $agent = $this->agent();
        $original = $agent->password;

        $this->post("/admin/accounts/agents/{$agent->id}/reset-password", [
            'password' => 'new-secure-password123',
        ])->assertRedirect();

        $this->assertNotSame($original, $agent->fresh()->password);
    }

    public function test_delete_is_blocked_when_the_account_has_orders(): void
    {
        $agent = $this->agent();
        $this->orderFor($agent);

        $this->delete("/admin/accounts/agents/{$agent->id}")->assertRedirect();

        $this->assertDatabaseHas('agents', ['id' => $agent->id]);
    }

    public function test_delete_succeeds_without_dependents(): void
    {
        $agent = $this->agent();

        $this->delete("/admin/accounts/agents/{$agent->id}")->assertRedirect();

        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }

    public function test_bulk_suspend_flags_selected_accounts(): void
    {
        $a = $this->agent('0551000001');
        $b = $this->agent('0551000002');

        $this->post('/admin/accounts/agents/bulk', ['action' => 'suspend', 'ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertFalse((bool) $a->fresh()->is_active);
        $this->assertFalse((bool) $b->fresh()->is_active);
    }

    public function test_export_streams_csv(): void
    {
        $this->agent();

        $response = $this->get('/admin/accounts/agents/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Kofi', $response->streamedContent());
    }

    public function test_search_narrows_the_list(): void
    {
        $this->agent('0551000001');
        Agent::create(['name' => 'Zara', 'phone' => '0551000002', 'password' => 'secret-1234', 'is_active' => true]);

        $this->get('/admin/accounts?type=agents&q=Zara')
            ->assertInertia(fn ($page) => $page->has('accounts', 1)->where('accounts.0.name', 'Zara'));
    }

    public function test_index_renders_last_activity_for_accounts_with_orders(): void
    {
        $agent = $this->agent();
        $this->orderFor($agent);

        $this->get('/admin/accounts?type=agents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('accounts.0.ordersCount', 1)
                ->whereNot('accounts.0.lastActivity', null));
    }

    private function orderFor(Agent $agent): Order
    {
        return $agent->orders()->create([
            'reference' => 'DS-'.strtoupper(uniqid()),
            'network' => 'mtn',
            'capacity_gb' => 5,
            'beneficiary_phone' => '0209000000',
            'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30,
            'seller_cost' => 25,
            'agent_cost' => 20,
            'base_cost' => 15,
            'status' => Order::STATUS_COMPLETED,
        ]);
    }
}
