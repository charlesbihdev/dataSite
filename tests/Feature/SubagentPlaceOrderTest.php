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

    public function test_guests_are_sent_to_the_subagent_login(): void
    {
        auth('subagent')->logout();

        $this->post(route('subagent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5])
            ->assertRedirect(route('subagent.login'));
        $this->post(route('subagent.topup'), ['amount' => 100])
            ->assertRedirect(route('subagent.login'));
    }
}
