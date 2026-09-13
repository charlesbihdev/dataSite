<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BaseCost;
use App\Models\DbhConfig;
use App\Models\Order;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaceOrderCartTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);

        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'mtn',
            'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 5.0, 'is_active' => true,
        ]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'cost_per_gb' => 3.0, 'is_active' => true]);

        $this->agent = Agent::factory()->create(['pricing_tier_id' => $tier->id]);
        $this->agent->walletOrCreate()->credit(100, 'topup');
        $this->actingAs($this->agent, 'agent');
    }

    private function fakeUpstreamCompleted(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 1, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
    }

    public function test_adding_a_bundle_prices_and_stores_a_cart_line(): void
    {
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5])
            ->assertRedirect(route('agent.dashboard'));

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->has('cart', 1)
                ->where('cart.0.network', 'mtn')
                ->where('cart.0.cost', 25) // 5 GB * 5.00/GB
                ->where('cartTotal', 25)
        );
    }

    public function test_invalid_phone_is_rejected_and_nothing_is_added(): void
    {
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '12345', 'bundle_size' => 5])
            ->assertSessionHasErrors('beneficiary_phone');

        $this->assertEmpty(session('agent_cart', []));
    }

    public function test_bulk_paste_adds_valid_lines_and_skips_the_rest(): void
    {
        $this->post(route('agent.cart.bulk'), [
            'bulk_orders_text' => "0559999999 5\ngarbage line\n0244000000 10",
        ])->assertRedirect(route('agent.dashboard'));

        $this->assertCount(2, session('agent_cart'));
    }

    public function test_checkout_places_orders_and_debits_the_wallet(): void
    {
        $this->fakeUpstreamCompleted();
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5]);

        $this->post(route('agent.cart.checkout'))->assertRedirect(route('agent.dashboard'));

        $order = Order::sole();
        $this->assertSame('portal', $order->source);
        $this->assertSame('0559999999', $order->beneficiary_phone);
        $this->assertSame(75.0, (float) $this->agent->walletOrCreate()->balance); // 100 - 25
        $this->assertEmpty(session('agent_cart', []));
    }

    public function test_checkout_rejects_when_balance_is_short_and_keeps_the_cart(): void
    {
        $this->agent->walletOrCreate()->debit(90, 'spend'); // leaves 10, order costs 25
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5]);

        $this->post(route('agent.cart.checkout'))->assertRedirect(route('agent.dashboard'));

        $this->assertSame(0, Order::count());
        $this->assertCount(1, session('agent_cart'));
        $this->assertSame(10.0, (float) $this->agent->walletOrCreate()->balance);
    }
}
