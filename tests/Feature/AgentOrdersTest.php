<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = Agent::factory()->create();
        $this->makeOrder('DS-1', 'mtn', Order::STATUS_COMPLETED);
        $this->makeOrder('DS-2', 'telecel', Order::STATUS_FAILED);
        $this->makeOrder('DS-3', 'mtn', Order::STATUS_PROCESSING);

        $this->actingAs($this->agent, 'agent');
    }

    private function makeOrder(string $ref, string $network, string $status): void
    {
        $this->agent->orders()->create([
            'reference' => $ref,
            'idempotency_key' => $ref,
            'source' => Order::SOURCE_PORTAL,
            'payment_status' => Order::PAYMENT_PAID,
            'network' => $network,
            'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567',
            'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30,
            'seller_cost' => 25,
            'agent_cost' => 20,
            'base_cost' => 15,
            'status' => $status,
        ]);
    }

    public function test_status_filter_scopes_the_table(): void
    {
        $this->get(route('agent.orders', ['status' => 'failed']))->assertInertia(
            fn (Assert $page) => $page
                ->where('filters.status', 'failed')
                ->has('orders.data', 1)
                ->where('orders.data.0.reference', 'DS-2')
        );
    }

    public function test_network_filter_scopes_the_table_and_stats(): void
    {
        $this->get(route('agent.orders', ['network' => 'mtn']))->assertInertia(
            fn (Assert $page) => $page
                ->where('filters.network', 'mtn')
                ->has('orders.data', 2)
                ->where('stats.count', 2)
        );
    }

    public function test_agent_can_retry_their_own_failed_order(): void
    {
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 30, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
        $this->agent->walletOrCreate()->credit(100, 'topup');

        // A failed prepaid order whose money was already refunded (payment no longer held).
        $order = $this->agent->orders()->where('reference', 'DS-2')->firstOrFail();
        $order->update(['payment_status' => Order::PAYMENT_AWAITING]);

        $this->post(route('agent.orders.retry', ['order' => $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(75.0, (float) $this->agent->walletOrCreate()->balance); // re-debited 25
    }

    public function test_agent_cannot_retry_another_agents_order(): void
    {
        $other = Agent::factory()->create();
        $foreign = $other->orders()->create([
            'reference' => 'DS-OTHER', 'idempotency_key' => 'DS-OTHER', 'source' => Order::SOURCE_PORTAL,
            'payment_status' => Order::PAYMENT_AWAITING, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_FAILED,
        ]);

        $this->post(route('agent.orders.retry', ['order' => $foreign->id]))->assertNotFound();
        $this->assertSame(Order::STATUS_FAILED, $foreign->refresh()->status);
    }

    public function test_agent_can_verify_and_dispatch_their_awaiting_storefront_order(): void
    {
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        \App\Models\PaymentGateway::query()->create([
            'gateway' => \App\Models\PaymentGateway::PAYSTACK, 'is_active' => true,
            'public_key' => 'pk', 'secret_key' => 'sk', 'currency' => 'GHS',
            'min_topup' => 1, 'max_topup' => 100000, 'charge_percent' => 0,
        ]);
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true, 'data' => ['status' => 'success', 'amount' => 3000, 'currency' => 'GHS', 'reference' => 'DS-AW1'],
            ]),
            'dbh.test/api/create_order' => Http::response([
                'success' => true, 'data' => ['requestId' => 31, 'orderStatus' => 'completed', 'price' => 15.0],
            ]),
        ]);

        $order = $this->agent->orders()->create([
            'reference' => 'DS-AW1', 'idempotency_key' => 'DS-AW1', 'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_AWAITING, 'gateway' => 'paystack', 'gateway_reference' => 'DS-AW1',
            'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15, 'status' => Order::STATUS_PENDING,
        ]);

        $this->post(route('agent.orders.verify-payment', ['order' => $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
    }

    public function test_only_failed_orders_can_be_retried(): void
    {
        $completed = $this->agent->orders()->where('reference', 'DS-1')->firstOrFail();

        $this->post(route('agent.orders.retry', ['order' => $completed->id]))->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $completed->refresh()->status);
    }
}
