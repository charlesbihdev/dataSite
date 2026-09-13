<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function agentWithStore(array $overrides = []): Agent
    {
        $agent = Agent::factory()->create(array_merge(['slug' => 'kofi-data', 'store_name' => 'Kofi Data'], $overrides));
        $agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 5, 'cost_price' => 20, 'selling_price' => 24, 'is_active' => true]);
        $agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 10, 'cost_price' => 40, 'selling_price' => 46, 'is_active' => true]);
        $agent->packagePrices()->create(['network' => 'at', 'capacity_gb' => 2, 'cost_price' => 8, 'selling_price' => 10, 'is_active' => false]);

        return $agent;
    }

    public function test_storefront_lists_only_active_packages(): void
    {
        $this->agentWithStore();

        $this->get(route('agent.storefront', ['agentSlug' => 'kofi-data']))->assertInertia(
            fn (Assert $page) => $page
                ->component('storefront/buy')
                ->where('store.name', 'Kofi Data')
                ->has('packages', 2)
                ->where('packages.0.price', 24)
        );
    }

    public function test_deactivated_store_is_not_reachable(): void
    {
        $this->agentWithStore(['store_active' => false]);

        $this->get(route('agent.storefront', ['agentSlug' => 'kofi-data']))->assertNotFound();
    }

    public function test_suspended_agent_store_is_not_reachable(): void
    {
        $this->agentWithStore(['is_active' => false]);

        $this->get(route('agent.storefront', ['agentSlug' => 'kofi-data']))->assertNotFound();
    }

    public function test_checkout_creates_an_awaiting_storefront_order(): void
    {
        $agent = $this->agentWithStore();

        $this->post(route('agent.storefront.checkout', ['agentSlug' => 'kofi-data']), [
            'beneficiary_phone' => '0241234567',
            'network' => 'mtn',
            'capacity_gb' => 5,
        ])->assertRedirectToRoute('agent.storefront.receipt', [
            'agentSlug' => 'kofi-data',
            'order' => Order::query()->latest('id')->first()->reference,
        ]);

        $order = Order::query()->firstOrFail();
        $this->assertSame(Order::SOURCE_STOREFRONT, $order->source);
        $this->assertSame(Order::PAYMENT_AWAITING, $order->payment_status);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(Order::CHANNEL_ONLINE, $order->channel);
        $this->assertSame('24.00', $order->customer_price);
        $this->assertSame('20.00', $order->seller_cost);
        $this->assertSame($agent->getKey(), $order->seller_id);
    }

    public function test_checkout_rejects_a_package_not_on_the_store(): void
    {
        $this->agentWithStore();

        // 20GB isn't listed on this store.
        $this->post(route('agent.storefront.checkout', ['agentSlug' => 'kofi-data']), [
            'beneficiary_phone' => '0241234567',
            'network' => 'mtn',
            'capacity_gb' => 20,
        ])->assertRedirect();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_rejects_a_number_that_does_not_match_the_network(): void
    {
        $this->agentWithStore();

        // AirtelTigo number (026) buying an MTN package.
        $this->post(route('agent.storefront.checkout', ['agentSlug' => 'kofi-data']), [
            'beneficiary_phone' => '0261234567',
            'network' => 'mtn',
            'capacity_gb' => 5,
        ])->assertRedirect();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_agent_can_toggle_their_store_off_and_on(): void
    {
        $agent = Agent::factory()->create(['store_active' => true]);

        $this->actingAs($agent, 'agent')->post(route('agent.referral.store'))->assertRedirect(route('agent.referral'));
        $this->assertFalse($agent->fresh()->store_active);

        $this->actingAs($agent, 'agent')->post(route('agent.referral.store'))->assertRedirect(route('agent.referral'));
        $this->assertTrue($agent->fresh()->store_active);
    }
}
