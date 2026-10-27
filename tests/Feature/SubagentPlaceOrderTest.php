<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentPackagePrice;
use App\Models\BalanceTopup;
use App\Models\BaseCost;
use App\Models\DbhConfig;
use App\Models\Earning;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PricingTier;
use App\Models\Subagent;
use App\Models\TierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Subagent Place-Order: bought at the agent's sub-agent price, paid from the subagent's own deposit
 * wallet (funded by gateway top-ups), with the parent agent earning the commission.
 */
class SubagentPlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    private Subagent $subagent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);

        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'mtn',
            'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 5.0, 'is_active' => true,
        ]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'cost_per_gb' => 3.0, 'is_active' => true]);

        $this->agent = Agent::factory()->create(['pricing_tier_id' => $tier->id]);
        // Agent pays 25 for 5GB on its tier and opens it to sub-agents at 30.
        AgentPackagePrice::create([
            'agent_id' => $this->agent->id, 'network' => 'mtn', 'capacity_gb' => 5,
            'cost_price' => 25, 'selling_price' => 35, 'subagent_price' => 30, 'is_active' => true,
        ]);

        $this->subagent = Subagent::create([
            'agent_id' => $this->agent->id, 'name' => 'Kofi Sub', 'phone' => '0551111111',
            'username' => 'kofisub', 'password' => 'secret123', 'is_active' => true,
        ]);
        $this->actingAs($this->subagent, 'subagent');
    }

    public function test_cart_line_is_priced_at_the_agents_subagent_price(): void
    {
        $this->post(route('subagent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5])
            ->assertRedirect(route('subagent.dashboard'));

        $this->get(route('subagent.dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->component('subagent/dashboard')
                ->has('cart', 1)
                ->where('cart.0.cost', 30)
                ->where('cartTotal', 30)
                ->where('networks.0.sizes', [5])
        );
    }

    public function test_a_bundle_the_agent_has_not_opened_to_subagents_is_rejected(): void
    {
        $this->post(route('subagent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 10])
            ->assertRedirect(route('subagent.dashboard'));

        $this->assertEmpty(session('agent_cart', []));
    }

    public function test_bulk_lines_for_unopened_bundles_are_reported_as_not_offered_by_the_agent(): void
    {
        $this->post(route('subagent.cart.bulk'), ['bulk_orders_text' => "0559999999 5\n0559999998 10"])
            ->assertSessionHas('inertia.flash_data.cartSkipped', fn (array $skipped): bool => count($skipped) === 1
                && $skipped[0]['row'] === 2
                && str_contains($skipped[0]['reason'], 'not opened this bundle to sub-agents'));

        $this->assertCount(1, session('agent_cart'));
    }

    public function test_dashboard_offers_only_opened_bundles_with_their_prices(): void
    {
        $this->get(route('subagent.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('networks.0.code', 'mtn')
            ->where('networks.0.sizes', [5])
            ->where('networks.0.prices', [5 => 30])
            ->where('networks.1.sizes', [])
        );
    }

    public function test_checkout_debits_the_subagent_wallet_and_pays_the_agent_commission(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 1, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
        $this->subagent->walletOrCreate()->credit(100, 'topup');

        $this->post(route('subagent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5]);
        $this->post(route('subagent.cart.checkout'))->assertRedirect(route('subagent.dashboard'));

        $order = Order::query()->sole();
        $this->assertTrue($order->seller->is($this->subagent));
        $this->assertSame(Order::SOURCE_PORTAL, $order->source);
        $this->assertEquals(30, (float) $order->seller_cost);
        $this->assertEquals(25, (float) $order->agent_cost);
        $this->assertEquals(70, (float) $this->subagent->wallet->fresh()->balance);
        $this->assertEquals(5, (float) $this->agent->earnings()->where('type', Earning::TYPE_COMMISSION)->sum('amount'));
    }

    public function test_checkout_with_a_short_wallet_places_nothing(): void
    {
        $this->post(route('subagent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5]);
        $this->post(route('subagent.cart.checkout'))->assertRedirect(route('subagent.dashboard'));

        $this->assertSame(0, Order::query()->count());
        $this->assertCount(1, session('agent_cart'));
    }

    public function test_topup_opens_a_checkout_with_a_synthesized_email_when_the_subagent_has_none(): void
    {
        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::PAYSTACK, 'is_active' => true, 'public_key' => 'pk', 'secret_key' => 'sk',
            'currency' => 'GHS', 'min_topup' => 10, 'max_topup' => 10000, 'charge_percent' => 0,
        ]);
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'PSTK_ref'],
        ])]);

        $this->post(route('subagent.topup'), ['amount' => 100])
            ->assertRedirect('https://checkout.paystack.com/xyz');

        $topup = BalanceTopup::query()->sole();
        $this->assertSame($this->subagent->walletOrCreate()->id, $topup->wallet_id);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with((string) $request['email'], '0551111111@')
            && str_contains($request['callback_url'], 'topup/callback'));
    }

    public function test_a_subagent_cannot_verify_someone_elses_topup(): void
    {
        $foreign = BalanceTopup::query()->create([
            'wallet_id' => $this->agent->walletOrCreate()->id, 'gateway' => PaymentGateway::PAYSTACK,
            'reference' => 'MTP_x', 'amount' => 50, 'charged_amount' => 50, 'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        $this->post(route('subagent.topup.verify', $foreign))->assertForbidden();
    }

    public function test_wallet_tab_lists_the_deposit_ledger_separately_from_earnings(): void
    {
        $this->subagent->walletOrCreate()->credit(40, 'topup', 'MTP_abc', 'Wallet top-up');

        $this->get(route('subagent.transactions', ['tab' => 'wallet']))->assertInertia(
            fn (Assert $page) => $page
                ->component('subagent/transactions')
                ->where('tab', 'wallet')
                ->where('stats.walletBalance', 40)
                ->where('stats.available', 0)
                ->has('transactions.data', 1)
                ->where('transactions.data.0.type', 'Wallet Top-up')
        );

        $this->get(route('subagent.transactions'))->assertInertia(
            fn (Assert $page) => $page->where('tab', 'earnings')->has('transactions.data', 0)
        );
    }

    public function test_wallet_history_paginates_ledger_and_topup_attempts_as_one_list(): void
    {
        $wallet = $this->subagent->walletOrCreate();
        foreach (range(1, 25) as $i) {
            $wallet->credit(1, 'topup', "MTP_paid_{$i}", 'Wallet top-up');
        }
        foreach (range(1, 10) as $i) {
            BalanceTopup::query()->create([
                'wallet_id' => $wallet->id, 'gateway' => PaymentGateway::PAYSTACK, 'reference' => "MTP_wait_{$i}",
                'amount' => 5, 'charged_amount' => 5, 'currency' => 'GHS', 'status' => BalanceTopup::STATUS_PENDING,
            ]);
        }

        $this->get(route('subagent.transactions', ['tab' => 'wallet']))->assertInertia(
            fn (Assert $page) => $page->has('transactions.data', 30)->where('transactions.total', 35)
        );

        $this->get(route('subagent.transactions', ['tab' => 'wallet', 'status' => 'pending', 'page' => 1]))->assertInertia(
            fn (Assert $page) => $page->has('transactions.data', 10)->where('transactions.total', 10)
        );

        $this->get(route('subagent.transactions', ['tab' => 'wallet', 'page' => 2]))->assertInertia(
            fn (Assert $page) => $page->has('transactions.data', 5)
                // Filters survive into the page links.
                ->where('transactions.links.1.url', fn ($url) => str_contains((string) $url, 'tab=wallet'))
        );
    }

    public function test_wallet_tab_shows_an_uncredited_topup_as_a_pending_row_with_verify(): void
    {
        $wallet = $this->subagent->walletOrCreate();
        $wallet->credit(40, 'topup', 'MTP_abc', 'Wallet top-up');
        $this->travel(1)->minutes();
        $pending = BalanceTopup::query()->create([
            'wallet_id' => $wallet->id, 'gateway' => PaymentGateway::PAYSTACK, 'reference' => 'MTP_waiting',
            'amount' => 25, 'charged_amount' => 25, 'currency' => 'GHS', 'status' => BalanceTopup::STATUS_PENDING,
        ]);

        $this->get(route('subagent.transactions', ['tab' => 'wallet']))->assertInertia(
            fn (Assert $page) => $page
                ->has('transactions.data', 2)
                ->where('transactions.data.0.reference', 'MTP_waiting')
                ->where('transactions.data.0.status', 'pending')
                ->where('transactions.data.0.topupId', $pending->id)
                ->where('transactions.data.1.status', 'completed')
                ->where('transactions.data.1.topupId', null)
        );

        $this->get(route('subagent.transactions', ['tab' => 'wallet', 'status' => 'pending']))->assertInertia(
            fn (Assert $page) => $page->has('transactions.data', 1)->where('transactions.data.0.reference', 'MTP_waiting')
        );
    }

    public function test_orders_page_hides_platform_costs_tags_source_and_counts_only_storefront_sales(): void
    {
        $order = fn (string $ref, string $source, float $price) => $this->subagent->orders()->create([
            'reference' => $ref, 'idempotency_key' => $ref, 'source' => $source,
            'payment_status' => Order::PAYMENT_PAID, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => $price, 'seller_cost' => 30, 'agent_cost' => 25, 'base_cost' => 15,
            'status' => Order::STATUS_COMPLETED,
        ]);
        $order('DS-SHOP', Order::SOURCE_STOREFRONT, 40);
        $order('DS-CART', Order::SOURCE_PORTAL, 30);

        $this->get(route('subagent.orders'))->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 2)
            ->missing('orders.data.0.base_cost')
            ->missing('orders.data.0.agent_cost')
            ->missing('orders.data.0.upstream_reference')
            ->where('stats.count', 2)
            ->where('stats.sales', 40) // the 30 Place-Order purchase is wallet spend, not a sale
            ->where('stats.profit', 10)
        );

        $this->get(route('subagent.orders', ['source' => 'portal']))->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.reference', 'DS-CART')
            ->where('orders.data.0.source', Order::SOURCE_PORTAL)
            ->where('stats.sales', 0)
        );
    }

    public function test_guests_are_sent_to_the_subagent_login(): void
    {
        auth('subagent')->logout();

        $this->post(route('subagent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5])
            ->assertRedirect(route('subagent.login'));
        $this->post(route('subagent.topup'), ['amount' => 100])
            ->assertRedirect(route('subagent.login'));
    }
}
