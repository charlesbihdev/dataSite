<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
