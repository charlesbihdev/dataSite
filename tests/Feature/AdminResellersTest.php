<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminResellersTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    private Subagent $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->agent = Agent::factory()->create(['name' => 'Agent Mensah']);
        $this->sub = $this->subagent($this->agent, 'Kofi Store', '0591110001');
    }

    private function subagent(Agent $agent, string $name, string $phone): Subagent
    {
        return $agent->subagents()->create(['name' => $name, 'phone' => $phone, 'password' => 'secret', 'is_active' => true]);
    }

    /**
     * Customer 12 → subagent cost 10.5 → agent cost 9.5 → DBH 9: shares 1.5 / 1.0 / 0.5.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function sale(Agent|Subagent $seller, string $ref, array $overrides = [], bool $withEarnings = true): Order
    {
        $order = $seller->orders()->create(array_merge([
            'reference' => $ref, 'idempotency_key' => $ref, 'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_PAID, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 12, 'seller_cost' => 10.5, 'agent_cost' => 9.5, 'base_cost' => 9,
            'status' => Order::STATUS_COMPLETED,
        ], $overrides));

        if ($withEarnings && $seller instanceof Subagent) {
            $status = $order->status === Order::STATUS_COMPLETED ? Earning::STATUS_CREDITED : Earning::STATUS_PENDING;
            $seller->earnings()->create(['order_id' => $order->id, 'type' => Earning::TYPE_SHOP_PROFIT,
                'amount' => round($order->customer_price - $order->seller_cost, 2), 'status' => $status]);
            $seller->agent->earnings()->create(['order_id' => $order->id, 'type' => Earning::TYPE_COMMISSION,
                'amount' => round($order->seller_cost - $order->agent_cost, 2), 'status' => $status]);
        }

        return $order;
    }

    public function test_guests_cannot_open_the_resellers_pages(): void
    {
        $this->get(route('admin.resellers'))->assertRedirect();
        $this->get(route('admin.resellers.profit-splits'))->assertRedirect();
    }

    public function test_tree_lists_agents_with_their_subagents_and_earnings(): void
    {
        $second = $this->subagent($this->agent, 'Ama Data', '0591110002');
        Agent::factory()->create(['name' => 'Lonely Agent']); // no subagents → not listed

        $this->sale($this->sub, 'DS-1');
        $this->sale($this->sub, 'DS-2', ['status' => Order::STATUS_PROCESSING]);
        $this->sale($second, 'DS-3');

        $this->actingAsAdmin();
        $this->get(route('admin.resellers'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/resellers/index')
            ->has('agents.data', 1)
            ->where('agents.data.0.name', 'Agent Mensah')
            ->where('agents.data.0.subagentCount', 2)
            ->where('agents.data.0.sales', 2)
            ->where('agents.data.0.agentEarned', 2)
            ->where('agents.data.0.agentPending', 1)
            ->where('agents.data.0.subagentEarned', 3)
            ->where('agents.data.0.subagents.1.name', 'Kofi Store')
            ->where('agents.data.0.subagents.1.subagentEarned', 1.5)
            ->where('agents.data.0.subagents.1.subagentPending', 1.5)
            ->where('stats.agents', 1)
            ->where('stats.subagents', 2)
            ->where('stats.agentEarned', 2)
            ->where('stats.subagentEarned', 3)
            ->where('stats.pending', 2.5));
    }

    public function test_tree_search_matches_subagent_names(): void
    {
        $other = Agent::factory()->create(['name' => 'Agent Owusu']);
        $this->subagent($other, 'Zed Bundles', '0591110009');

        $this->actingAsAdmin();
        $this->get(route('admin.resellers', ['q' => 'Zed']))->assertInertia(fn (Assert $page) => $page
            ->has('agents.data', 1)
            ->where('agents.data.0.name', 'Agent Owusu'));
    }

    public function test_splits_show_each_party_share_and_pass_the_check(): void
    {
        $this->sale($this->sub, 'DS-1', ['upstream_cost' => 9]);
        $this->sale($this->agent, 'DS-AGENT'); // agent's own sale is not a subagent settlement
        $this->sale($this->sub, 'DS-UNPAID', ['payment_status' => Order::PAYMENT_AWAITING, 'status' => Order::STATUS_PENDING], false);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/resellers/profit-splits')
            ->has('splits.data', 1)
            ->where('splits.data.0.reference', 'DS-1')
            ->where('splits.data.0.agent', 'Agent Mensah')
            ->where('splits.data.0.subagentShare', 1.5)
            ->where('splits.data.0.agentShare', 1)
            ->where('splits.data.0.platformShare', 0.5)
            ->where('splits.data.0.dbhCost', 9)
            ->where('splits.data.0.dbhActual', true)
            ->where('splits.data.0.settlement', 'credited')
            ->where('splits.data.0.issues', [])
            ->where('stats.customerPaid', 12)
            ->where('stats.problems', 0));
    }

    public function test_wrong_stored_earning_and_negative_share_are_flagged(): void
    {
        $this->sale($this->sub, 'DS-OK');

        $bad = $this->sale($this->sub, 'DS-BAD');
        Earning::query()->where('order_id', $bad->id)->where('type', Earning::TYPE_COMMISSION)->update(['amount' => 0.8]);

        // Subagent priced below the agent's own cost: the agent's cut goes negative.
        $neg = $this->sale($this->sub, 'DS-NEG', ['seller_cost' => 9, 'agent_cost' => 9.5], false);
        $this->sub->earnings()->create(['order_id' => $neg->id, 'type' => Earning::TYPE_SHOP_PROFIT, 'amount' => 3, 'status' => Earning::STATUS_CREDITED]);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits', ['problems' => 1]))->assertInertia(fn (Assert $page) => $page
            ->has('splits.data', 2)
            ->where('splits.data.0.reference', 'DS-NEG')
            ->where('splits.data.0.issues', ['Agent share is negative (-0.50)'])
            ->where('splits.data.1.reference', 'DS-BAD')
            ->where('splits.data.1.issues', ['Stored Agent earning 0.80, expected 1.00'])
            ->where('stats.problems', 2));
    }

    public function test_missing_earning_and_wrong_earner_are_flagged(): void
    {
        $this->sale($this->sub, 'DS-MISSING', [], false);

        $moved = $this->sale($this->sub, 'DS-WRONG');
        $stranger = Agent::factory()->create();
        Earning::query()->where('order_id', $moved->id)->where('type', Earning::TYPE_COMMISSION)->update(['earner_id' => $stranger->id]);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits', ['problems' => 1]))->assertInertia(fn (Assert $page) => $page
            ->has('splits.data', 2)
            ->where('splits.data.0.issues', ['Agent earning was credited to the wrong account'])
            ->where('splits.data.1.issues', ['Subagent earning missing (expected 1.50)', 'Agent earning missing (expected 1.00)']));
    }

    public function test_splits_filter_by_agent_and_settlement(): void
    {
        $other = Agent::factory()->create();
        $this->sale($this->subagent($other, 'Other Sub', '0591110003'), 'DS-OTHER');
        $this->sale($this->sub, 'DS-MINE', ['status' => Order::STATUS_PROCESSING]);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits', ['agent' => $this->agent->id]))
            ->assertInertia(fn (Assert $page) => $page->has('splits.data', 1)->where('splits.data.0.reference', 'DS-MINE'));

        $this->get(route('admin.resellers.profit-splits', ['settlement' => 'credited']))
            ->assertInertia(fn (Assert $page) => $page->has('splits.data', 1)->where('splits.data.0.reference', 'DS-OTHER'));
    }

    public function test_splits_export_streams_csv(): void
    {
        $this->sale($this->sub, 'DS-1');

        $this->actingAsAdmin();
        $response = $this->get(route('admin.resellers.profit-splits.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Subagent share', $csv);
        $this->assertStringContainsString('DS-1', $csv);
        $this->assertStringContainsString('OK', $csv);
    }
}
