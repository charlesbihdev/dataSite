<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = Agent::factory()->create();
        $order = $this->agent->orders()->create([
            'reference' => 'DS-W1', 'idempotency_key' => 'DS-W1', 'source' => Order::SOURCE_PORTAL,
            'payment_status' => Order::PAYMENT_PAID, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_COMPLETED,
        ]);
        $this->agent->earnings()->create([
            'order_id' => $order->id, 'type' => Earning::TYPE_COMMISSION,
            'amount' => 100, 'status' => Earning::STATUS_CREDITED, 'credited_at' => now(),
        ]);

        $this->actingAs($this->agent, 'agent');
    }

    public function test_page_shows_available_balance_and_enabled_methods(): void
    {
        $this->get(route('agent.withdrawals'))->assertInertia(
            fn (Assert $page) => $page
                ->component('agent/withdrawals')
                ->where('stats.available', 100)
                ->where('methods.0.enabled', true)
        );
    }

    public function test_request_creates_a_pending_withdrawal_and_reserves_the_balance(): void
    {
        $this->post(route('agent.withdrawals.store'), ['method' => 'momo', 'amount' => 40, 'destination' => '0551234567'])
            ->assertRedirect(route('agent.withdrawals'));

        $w = Withdrawal::sole();
        $this->assertSame(Withdrawal::STATUS_PENDING, $w->status);
        $this->assertSame('momo', $w->method);
        $this->assertSame(60.0, $this->agent->earningsBalance()); // 100 - 40 reserved
    }

    public function test_request_below_the_method_minimum_is_rejected(): void
    {
        $this->post(route('agent.withdrawals.store'), ['method' => 'momo', 'amount' => 5, 'destination' => '0551234567']);

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_request_above_available_balance_is_rejected(): void
    {
        $this->post(route('agent.withdrawals.store'), ['method' => 'momo', 'amount' => 500, 'destination' => '0551234567']);

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_agent_can_cancel_a_pending_withdrawal(): void
    {
        $w = $this->agent->withdrawals()->create([
            'amount' => 40, 'method' => 'momo', 'destination' => '0551234567',
            'status' => Withdrawal::STATUS_PENDING, 'reference' => 'WD-CANCELME',
        ]);
        $this->assertSame(60.0, $this->agent->earningsBalance());

        $this->post(route('agent.withdrawals.cancel', $w))->assertRedirect(route('agent.withdrawals'));

        $this->assertSame(Withdrawal::STATUS_REJECTED, $w->fresh()->status);
        $this->assertSame(100.0, $this->agent->earningsBalance()); // reservation freed
    }
}
