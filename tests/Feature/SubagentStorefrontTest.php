<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubagentStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::PAYSTACK,
            'is_active' => true,
            'public_key' => 'pk_test', 'secret_key' => 'sk_test',
            'currency' => 'GHS', 'min_topup' => 1, 'max_topup' => 100000, 'charge_percent' => 0,
        ]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/redirect', 'reference' => 'ps_ref'],
            ]),
        ]);
    }

    private function subagentWithStore(array $overrides = []): Subagent
    {
        $agent = Agent::factory()->create();
        // The agent opens the MTN 5GB package to sub-agents at 20 (their own cost is 18).
        $agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 5, 'cost_price' => 18, 'selling_price' => 24, 'subagent_price' => 20, 'is_active' => true]);

        $subagent = Subagent::create(array_merge([
            'agent_id' => $agent->id, 'name' => 'Kwame', 'phone' => '0247000001',
            'email' => 'kwame@ex.com', 'username' => 'kwame', 'slug' => 'kwame-data',
            'store_name' => 'Kwame Data', 'password' => 'secret', 'is_active' => true, 'store_active' => true,
        ], $overrides));

        // The subagent resells the 5GB at 22 (cost 20 = the agent's sub-agent price); the AT one is off.
        $subagent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 5, 'cost_price' => 20, 'selling_price' => 22, 'is_active' => true]);
        $subagent->packagePrices()->create(['network' => 'at', 'capacity_gb' => 2, 'cost_price' => 8, 'selling_price' => 10, 'is_active' => false]);

        return $subagent;
    }

    public function test_storefront_lists_only_active_packages(): void
    {
        $this->subagentWithStore();

        $this->get(route('subagent.storefront', ['subagentSlug' => 'kwame-data']))->assertInertia(
            fn (Assert $page) => $page
                ->component('subagent/storefront/buy')
                ->where('store.name', 'Kwame Data')
                ->has('packages', 1)
                ->where('packages.0.price', 22)
        );
    }

    public function test_storefront_resolves_by_username_too(): void
    {
        $this->subagentWithStore();

        // The public link is /{username}; resolving by username (not just the derived slug) must work.
        $this->get(route('subagent.storefront', ['subagentSlug' => 'kwame']))
            ->assertInertia(fn (Assert $page) => $page->component('subagent/storefront/buy'));
    }

    public function test_deactivated_store_is_not_reachable(): void
    {
        $this->subagentWithStore(['store_active' => false]);

        $this->get(route('subagent.storefront', ['subagentSlug' => 'kwame-data']))->assertNotFound();
    }

    public function test_suspended_subagent_store_is_not_reachable(): void
    {
        $this->subagentWithStore(['is_active' => false]);

        $this->get(route('subagent.storefront', ['subagentSlug' => 'kwame-data']))->assertNotFound();
    }

    public function test_checkout_creates_an_awaiting_order_with_the_three_way_cascade(): void
    {
        $subagent = $this->subagentWithStore();

        $this->post(route('subagent.storefront.checkout', ['subagentSlug' => 'kwame-data']), [
            'beneficiary_phone' => '0241234567',
            'network' => 'mtn',
            'capacity_gb' => 5,
        ])->assertRedirect('https://checkout.paystack.com/redirect');

        $order = Order::query()->firstOrFail();
        $this->assertSame(Order::SOURCE_STOREFRONT, $order->source);
        $this->assertSame(Order::PAYMENT_AWAITING, $order->payment_status);
        $this->assertSame(Order::CHANNEL_ONLINE, $order->channel);
        $this->assertSame($subagent->getKey(), $order->seller_id);
        // Cascade drives the split: customer 22 → subagent cost 20 → agent cost 18 → platform base.
        $this->assertSame('22.00', $order->customer_price);
        $this->assertSame('20.00', $order->seller_cost);
        $this->assertSame('18.00', $order->agent_cost);
    }

    public function test_checkout_rejects_a_package_not_on_the_store(): void
    {
        $this->subagentWithStore();

        // 10GB isn't listed on this store.
        $this->post(route('subagent.storefront.checkout', ['subagentSlug' => 'kwame-data']), [
            'beneficiary_phone' => '0241234567',
            'network' => 'mtn',
            'capacity_gb' => 10,
        ])->assertRedirect();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_rejects_a_number_that_does_not_match_the_network(): void
    {
        $this->subagentWithStore();

        $this->post(route('subagent.storefront.checkout', ['subagentSlug' => 'kwame-data']), [
            'beneficiary_phone' => '0261234567', // AirtelTigo number buying MTN
            'network' => 'mtn',
            'capacity_gb' => 5,
        ])->assertRedirect();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_track_finds_a_customer_order_by_phone_scoped_to_this_store(): void
    {
        $this->subagentWithStore();
        $this->post(route('subagent.storefront.checkout', ['subagentSlug' => 'kwame-data']), [
            'beneficiary_phone' => '0241234567', 'network' => 'mtn', 'capacity_gb' => 5,
        ]);

        $this->get(route('subagent.storefront.track', ['subagentSlug' => 'kwame-data', 'by' => 'phone', 'phone' => '0241234567']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('subagent/storefront/track')
                ->where('by', 'phone')
                ->has('orders', 1)
                ->where('orders.0.phone', '0241234567'));
    }
}
