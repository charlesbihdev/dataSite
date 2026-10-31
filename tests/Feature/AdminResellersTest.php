<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use App\Models\Withdrawal;
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

    public function test_tree_shows_each_persons_own_money(): void
    {
        $this->sale($this->sub, 'DS-1');
        $own = $this->sale($this->agent, 'DS-OWN', ['customer_price' => 30, 'seller_cost' => 20]);
        $this->agent->earnings()->create(['order_id' => $own->id, 'type' => Earning::TYPE_SHOP_PROFIT, 'amount' => 10, 'status' => Earning::STATUS_CREDITED]);
        $this->agent->withdrawals()->create(['amount' => 4, 'status' => Withdrawal::STATUS_PAID, 'method' => 'momo', 'destination' => '0241234567', 'reference' => 'WD-1']);
        $this->agent->withdrawals()->create(['amount' => 2, 'status' => Withdrawal::STATUS_PENDING, 'method' => 'momo', 'destination' => '0241234567', 'reference' => 'WD-2']);
        $this->agent->withdrawals()->create(['amount' => 1, 'status' => Withdrawal::STATUS_REJECTED, 'method' => 'momo', 'destination' => '0241234567', 'reference' => 'WD-3']);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers'))->assertInertia(fn (Assert $page) => $page
            ->where('agents.data.0.ownSales', 1)
            ->where('agents.data.0.ownRevenue', 30)
            ->where('agents.data.0.ownProfit', 10)
            ->where('agents.data.0.agentEarned', 1)
            ->where('agents.data.0.totalEarned', 11)
            ->where('agents.data.0.withdrawn', 4)
            ->where('agents.data.0.requested', 2)
            ->where('agents.data.0.available', fn ($v) => (float) $v === $this->agent->earningsBalance())
            ->where('agents.data.0.available', 5)
            ->where('agents.data.0.subagents.0.totalEarned', 1.5)
            ->where('agents.data.0.subagents.0.available', 1.5)
            ->where('stats.agentShopProfit', 10));
    }

    public function test_agents_without_subagents_are_listed(): void
    {
        Agent::factory()->create(['name' => 'Solo Shop']);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers', ['q' => 'Solo']))->assertInertia(fn (Assert $page) => $page
            ->has('agents.data', 1)
            ->where('agents.data.0.name', 'Solo Shop')
            ->where('agents.data.0.subagentCount', 0)
            ->where('agents.data.0.totalEarned', 0));
    }

    public function test_date_filter_changes_earnings_but_not_the_available_balance(): void
    {
        $old = $this->sale($this->agent, 'DS-OLD', ['customer_price' => 30, 'seller_cost' => 20]);
        $this->agent->earnings()->create(['order_id' => $old->id, 'type' => Earning::TYPE_SHOP_PROFIT, 'amount' => 10, 'status' => Earning::STATUS_CREDITED]);
        Order::query()->whereKey($old->id)->update(['created_at' => now()->subDays(40)]);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers', ['range' => 'custom', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agents.data.0.ownProfit', 0)
                ->where('agents.data.0.totalEarned', 0)
                ->where('agents.data.0.available', 10));
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

    /** An agent's own shop sale, with the profit they earned on it. */
    private function ownSale(string $ref, array $overrides = []): Order
    {
        $order = $this->sale($this->agent, $ref, $overrides + ['agent_cost' => 10.5]);

        if ($order->customer_price > $order->seller_cost) {
            $this->agent->earnings()->create(['order_id' => $order->id, 'type' => Earning::TYPE_SHOP_PROFIT,
                'amount' => round($order->customer_price - $order->seller_cost, 2),
                'status' => $order->status === Order::STATUS_COMPLETED ? Earning::STATUS_CREDITED : Earning::STATUS_PENDING]);
        }

        return $order;
    }

    public function test_own_scope_lists_the_agents_own_sales_and_keeps_the_subagent_scope_apart(): void
    {
        $this->ownSale('DS-OWN');
        $this->ownSale('DS-OWN-PENDING', ['status' => Order::STATUS_PROCESSING]);
        $this->sale($this->sub, 'DS-SUB');

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits', ['scope' => 'own', 'agent' => $this->agent->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('splits.data', 2)
                ->where('splits.data.1.reference', 'DS-OWN')
                ->where('splits.data.1.agent', 'Agent Mensah')
                ->where('splits.data.1.sellerCost', 10.5)
                ->where('splits.data.1.subagentShare', 1.5)
                ->where('splits.data.1.issues', [])
                ->where('stats.profitCredited', 1.5)
                ->where('stats.profitPending', 1.5)
                ->where('stats.problems', 0));

        $this->get(route('admin.resellers.profit-splits', ['agent' => $this->agent->id]))
            ->assertInertia(fn (Assert $page) => $page->has('splits.data', 1)->where('splits.data.0.reference', 'DS-SUB'));
    }

    public function test_own_sales_total_matches_the_shop_profit_on_the_resellers_table(): void
    {
        $this->ownSale('DS-A');
        $this->ownSale('DS-B', ['customer_price' => 20]);
        $this->ownSale('DS-FAILED', ['status' => Order::STATUS_FAILED]);

        $this->actingAsAdmin();
        $tableProfit = null;
        $this->get(route('admin.resellers'))->assertInertia(function (Assert $page) use (&$tableProfit) {
            $tableProfit = $page->toArray()['props']['agents']['data'][0]['ownProfit'];
        });

        $this->get(route('admin.resellers.profit-splits', ['scope' => 'own', 'agent' => $this->agent->id]))
            ->assertInertia(fn (Assert $page) => $page->where('stats.profitCredited', $tableProfit));
    }

    public function test_only_sales_with_profit_hides_wallet_orders(): void
    {
        $this->ownSale('DS-SHOP');
        $this->ownSale('DS-WALLET', ['customer_price' => 10.5]);
        $this->sale($this->sub, 'DS-SUB-WALLET', ['customer_price' => 10.5]);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits', ['scope' => 'own', 'profit_only' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('splits.data', 1)->where('splits.data.0.reference', 'DS-SHOP'));
        $this->get(route('admin.resellers.profit-splits', ['profit_only' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('splits.data', 0));
    }

    public function test_own_sale_problems_are_flagged_the_same_in_the_row_and_the_filter(): void
    {
        $this->ownSale('DS-OK');
        $wrong = $this->ownSale('DS-WRONG');
        Earning::query()->where('order_id', $wrong->id)->update(['amount' => 9]);
        $this->sale($this->agent, 'DS-MISSING', ['agent_cost' => 10.5]);
        $this->ownSale('DS-BELOW-DBH', ['seller_cost' => 8, 'agent_cost' => 8, 'customer_price' => 12]);

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits', ['scope' => 'own', 'problems' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('splits.data', 3)
                ->where('stats.problems', 3)
                ->where('splits.data.0.issues', ['Platform share is negative (-1.00)'])
                ->where('splits.data.1.issues', ['Shop profit earning missing (expected 1.50)'])
                ->where('splits.data.2.issues', ['Stored Shop profit earning 9.00, expected 1.50']));
    }

    public function test_own_sales_search_and_export(): void
    {
        $this->ownSale('DS-FINDME');
        $this->ownSale('DS-OTHER');

        $this->actingAsAdmin();
        $this->get(route('admin.resellers.profit-splits', ['scope' => 'own', 'q' => 'FINDME']))
            ->assertInertia(fn (Assert $page) => $page->has('splits.data', 1));

        $csv = $this->get(route('admin.resellers.profit-splits.export', ['scope' => 'own']))->streamedContent();
        $this->assertStringContainsString('Shop profit', $csv);
        $this->assertStringContainsString('DS-FINDME', $csv);
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
