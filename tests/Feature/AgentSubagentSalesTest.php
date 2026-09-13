<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Order;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentSubagentSalesTest extends TestCase
{
    use RefreshDatabase;

    private function sale(Subagent $seller, string $ref, string $status, float $seller_cost, float $agent_cost): void
    {
        $seller->orders()->create([
            'reference' => $ref, 'idempotency_key' => $ref, 'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_PAID, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 40, 'seller_cost' => $seller_cost, 'agent_cost' => $agent_cost, 'base_cost' => 15,
            'status' => $status, 'completed_at' => $status === Order::STATUS_COMPLETED ? now() : null,
        ]);
    }

    public function test_shows_only_this_agents_subagent_sales_with_margin(): void
    {
        $agent = Agent::factory()->create();
        $sub = $agent->subagents()->create(['name' => 'Karl', 'phone' => '0591112221', 'password' => 'secret', 'is_active' => true]);
        $this->sale($sub, 'SA-1', Order::STATUS_COMPLETED, 30, 25); // margin 5
        $this->sale($sub, 'SA-2', Order::STATUS_PROCESSING, 30, 25);

        // Another agent's sub-agent sale must not leak in.
        $other = Agent::factory()->create();
        $otherSub = $other->subagents()->create(['name' => 'X', 'phone' => '0599998887', 'password' => 'secret', 'is_active' => true]);
        $this->sale($otherSub, 'SA-X', Order::STATUS_COMPLETED, 30, 25);

        $this->actingAs($agent, 'agent')->get(route('agent.subagent-sales'))->assertInertia(
            fn (Assert $page) => $page
                ->component('agent/subagent-sales')
                ->has('orders.data', 2)
                ->where('stats.total', 2)
                ->where('stats.delivered', 1)
                ->where('stats.placed', 1)
                ->where('stats.margin30d', 5) // only the completed one
                ->where('orders.data.1.margin', 5)
        );
    }

    public function test_status_filter_scopes_the_list(): void
    {
        $agent = Agent::factory()->create();
        $sub = $agent->subagents()->create(['name' => 'Karl', 'phone' => '0591112222', 'password' => 'secret', 'is_active' => true]);
        $this->sale($sub, 'SA-1', Order::STATUS_COMPLETED, 30, 25);
        $this->sale($sub, 'SA-2', Order::STATUS_PROCESSING, 30, 25);

        $this->actingAs($agent, 'agent')->get(route('agent.subagent-sales', ['status' => 'completed']))->assertInertia(
            fn (Assert $page) => $page->where('filters.status', 'completed')->has('orders.data', 1)->where('stats.total', 1)
        );
    }
}
