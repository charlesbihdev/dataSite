<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A usable Paystack gateway + a faked hosted-checkout init so storefront checkout can open a
        // payment. (No preventStrayRequests: page renders may fall back from SSR gracefully.)
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
                ->component('agent/storefront/buy')
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

        // Checkout now opens a gateway checkout and redirects the customer there.
        $this->post(route('agent.storefront.checkout', ['agentSlug' => 'kofi-data']), [
            'beneficiary_phone' => '0241234567',
            'network' => 'mtn',
            'capacity_gb' => 5,
        ])->assertRedirect('https://checkout.paystack.com/redirect');

        $order = Order::query()->firstOrFail();
        $this->assertSame(Order::SOURCE_STOREFRONT, $order->source);
        $this->assertSame(Order::PAYMENT_AWAITING, $order->payment_status);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(Order::CHANNEL_ONLINE, $order->channel);
        $this->assertSame('24.00', $order->customer_price);
        $this->assertSame('20.00', $order->seller_cost);
        $this->assertSame(PaymentGateway::PAYSTACK, $order->gateway);
        $this->assertSame($agent->getKey(), $order->seller_id);
    }

    public function test_checkout_from_an_inertia_visit_redirects_to_the_gateway(): void
    {
        // Real browser flow: an Inertia visit gets a 409 + X-Inertia-Location (not a plain redirect).
        $this->agentWithStore();

        $this->post(route('agent.storefront.checkout', ['agentSlug' => 'kofi-data']), [
            'beneficiary_phone' => '0241234567',
            'network' => 'mtn',
            'capacity_gb' => 5,
        ], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/redirect');
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

    public function test_track_finds_a_customer_order_by_phone(): void
    {
        $this->agentWithStore();
        $this->placeOrder('0241234567');

        $this->get(route('agent.storefront.track', ['agentSlug' => 'kofi-data', 'by' => 'phone', 'phone' => '0241234567']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('agent/storefront/track')
                ->where('by', 'phone')
                ->has('orders', 1)
                ->where('orders.0.phone', '0241234567')
            );
    }

    public function test_track_finds_a_customer_order_by_reference(): void
    {
        $this->agentWithStore();
        $reference = $this->placeOrder('0241234567')->reference;

        $this->get(route('agent.storefront.track', ['agentSlug' => 'kofi-data', 'by' => 'reference', 'reference' => strtolower($reference)]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('agent/storefront/track')
                ->where('by', 'reference')
                ->has('orders', 1)
                ->where('orders.0.reference', $reference)
            );
    }

    public function test_track_only_returns_orders_for_the_current_store(): void
    {
        $this->agentWithStore();
        $this->placeOrder('0241234567');

        // A different agent's store must never surface another store's orders for the same number.
        Agent::factory()->create(['slug' => 'ama-data', 'store_name' => 'Ama Data']);

        $this->get(route('agent.storefront.track', ['agentSlug' => 'ama-data', 'by' => 'phone', 'phone' => '0241234567']))
            ->assertInertia(fn (Assert $page) => $page->has('orders', 0));
    }

    public function test_payment_callback_verifies_and_dispatches_the_order(): void
    {
        $this->fakeGatewayAndUpstream();
        $this->agentWithStore();
        $order = $this->placeOrder('0241234567');

        $this->get(route('agent.storefront.callback', ['agentSlug' => 'kofi-data', 'reference' => $order->reference]))
            ->assertRedirectToRoute('agent.storefront.receipt', ['agentSlug' => 'kofi-data', 'order' => $order->reference]);

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
    }

    public function test_paystack_webhook_dispatches_a_storefront_order(): void
    {
        $this->fakeGatewayAndUpstream();
        $this->agentWithStore();
        $order = $this->placeOrder('0241234567');

        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $order->reference, 'status' => 'success']]);
        $signature = hash_hmac('sha512', $payload, 'sk_test');

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
    }

    /**
     * Fake the whole online path: gateway init + verify (amount matches the 5GB @ GHS 24 package)
     * and the upstream dispatch that fulfillPaid triggers once the payment clears.
     */
    private function fakeGatewayAndUpstream(): void
    {
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/redirect', 'reference' => 'ps_ref'],
            ]),
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 2400, 'currency' => 'GHS', 'reference' => 'ps_ref'],
            ]),
            'dbh.test/api/create_order' => Http::response([
                'success' => true, 'data' => ['requestId' => 90, 'orderStatus' => 'completed', 'price' => 20.0],
            ]),
        ]);
    }

    private function placeOrder(string $phone): Order
    {
        $this->post(route('agent.storefront.checkout', ['agentSlug' => 'kofi-data']), [
            'beneficiary_phone' => $phone,
            'network' => 'mtn',
            'capacity_gb' => 5,
        ]);

        return Order::query()->latest('id')->firstOrFail();
    }
}
