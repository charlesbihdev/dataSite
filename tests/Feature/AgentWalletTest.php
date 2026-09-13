<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Services\Orders\OrderDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentWalletTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = Agent::factory()->create();
        $wallet = $this->agent->walletOrCreate();
        $wallet->credit(100, 'topup', 'PSTK-1', 'Admin funding');           // ADMIN, credit
        $wallet->debit(25, OrderDispatchService::TXN_PURCHASE, 'DS-ABC', 'Bundle'); // USER, debit
        $wallet->debit(10, 'adjustment', 'ADJ-1', 'Admin deduction');       // ADMIN, debit

        $this->actingAs($this->agent, 'agent');
    }

    public function test_wallet_page_shows_balance_and_full_ledger(): void
    {
        $this->get(route('agent.transactions'))->assertInertia(
            fn (Assert $page) => $page
                ->component('agent/transactions')
                ->where('balance', 65) // 100 - 25 - 10
                ->has('transactions.data', 3)
        );
    }

    public function test_source_is_derived_as_admin_or_user(): void
    {
        $this->get(route('agent.transactions'))->assertInertia(function (Assert $page) {
            $rows = collect($page->toArray()['props']['transactions']['data']);

            // Newest first: adjustment (admin), purchase (user), topup (admin).
            $this->assertSame(['admin', 'user', 'admin'], $rows->pluck('source')->all());
            $this->assertSame(['debit', 'debit', 'credit'], $rows->pluck('direction')->all());
        });
    }

    public function test_type_filter_scopes_to_data_purchases(): void
    {
        $this->get(route('agent.transactions', ['type' => 'purchase']))->assertInertia(
            fn (Assert $page) => $page
                ->where('filters.type', 'purchase')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.type', 'Data Purchase')
                ->where('transactions.data.0.orderReference', 'DS-ABC')
        );
    }

    public function test_source_filter_scopes_to_admin_movements(): void
    {
        // ADMIN = the topup + the adjustment; the purchase (USER) is excluded.
        $this->get(route('agent.transactions', ['source' => 'admin']))->assertInertia(
            fn (Assert $page) => $page
                ->where('filters.source', 'admin')
                ->has('transactions.data', 2)
                ->where('transactions.data.0.source', 'admin')
                ->where('transactions.data.1.source', 'admin')
        );
    }
}
