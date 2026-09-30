<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Withdrawal;
use App\Models\WithdrawalConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalLimitsTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = Agent::factory()->create();
        $order = $this->agent->orders()->create([
            'reference' => 'DS-L1', 'idempotency_key' => 'DS-L1', 'source' => Order::SOURCE_PORTAL,
            'payment_status' => Order::PAYMENT_PAID, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_COMPLETED,
        ]);
        $this->agent->earnings()->create([
            'order_id' => $order->id, 'type' => Earning::TYPE_COMMISSION,
            'amount' => 500, 'status' => Earning::STATUS_CREDITED, 'credited_at' => now(),
        ]);

        $this->actingAs($this->agent, 'agent');
    }

    private function request(float $amount)
    {
        return $this->post(route('agent.withdrawals.store'), [
            'method' => 'momo', 'amount' => $amount, 'destination' => '0551234567',
        ]);
    }

    public function test_config_defaults_to_minimum_of_20_and_no_maximum(): void
    {
        $this->assertSame(20.0, WithdrawalConfig::minAmount());
        $this->assertNull(WithdrawalConfig::maxAmount());
    }

    public function test_request_below_the_configured_minimum_is_rejected(): void
    {
        WithdrawalConfig::create(['min_amount' => 30, 'max_amount' => null]);

        $this->request(25);

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_request_above_the_configured_maximum_is_rejected(): void
    {
        WithdrawalConfig::create(['min_amount' => 20, 'max_amount' => 50]);

        $this->request(60);

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_request_within_the_configured_limits_succeeds(): void
    {
        WithdrawalConfig::create(['min_amount' => 20, 'max_amount' => 80]);

        $this->request(50)->assertRedirect(route('agent.withdrawals'));

        $this->assertSame(1, Withdrawal::count());
        $this->assertSame(50.0, (float) Withdrawal::sole()->amount);
    }
}
