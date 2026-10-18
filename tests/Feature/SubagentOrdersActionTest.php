<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Earning;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PricingTier;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubagentOrdersActionTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;
    private Subagent $subagent;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = PricingTier::create(['name' => 'Std', 'is_active' => true]);
        $this->agent = Agent::create([
            'pricing_tier_id' => $tier->id,
            'name' => 'Main Agent',
            'phone' => '0550000001',
            'email' => 'agent@ex.com',
            'username' => 'mainagent',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $this->subagent = Subagent::create([
            'agent_id' => $this->agent->id,
            'name' => 'Sub Agent',
            'phone' => '0240000001',
            'email' => 'subagent@ex.com',
            'username' => 'subagent1',
            'slug' => 'subagent1',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    public function test_subagent_can_verify_and_dispatch_their_awaiting_storefront_order(): void
    {
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::PAYSTACK,
            'is_active' => true,
            'public_key' => 'pk',
            'secret_key' => 'sk',
            'currency' => 'GHS',
            'min_topup' => 1,
            'max_topup' => 100000,
            'charge_percent' => 0,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 3500,
                    'currency' => 'GHS',
                    'reference' => 'DS-SUB-AW1',
                ],
            ]),
            'dbh.test/api/create_order' => Http::response([
                'success' => true,
                'data' => [
                    'requestId' => 45,
                    'orderStatus' => 'completed',
                    'price' => 18.0,
                    'transactionReference' => 'DBH-SUB-TX-1',
                ],
            ]),
        ]);

        $order = $this->subagent->orders()->create([
            'reference' => 'DS-SUB-AW1',
            'idempotency_key' => 'DS-SUB-AW1',
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_AWAITING,
            'gateway' => 'paystack',
            'gateway_reference' => 'DS-SUB-AW1',
            'network' => 'mtn',
            'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 35,
            'seller_cost' => 28,
            'agent_cost' => 22,
            'base_cost' => 18,
            'status' => Order::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->subagent, 'subagent')
            ->post(route('subagent.orders.verify-payment', ['order' => $order->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);

        // Earnings created for both subagent and agent
        $this->assertDatabaseHas('earnings', [
            'order_id' => $order->id,
            'earner_type' => $this->subagent->getMorphClass(),
            'earner_id' => $this->subagent->id,
            'amount' => 7.00, // 35 - 28
        ]);

        $this->assertDatabaseHas('earnings', [
            'order_id' => $order->id,
            'earner_type' => $this->agent->getMorphClass(),
            'earner_id' => $this->agent->id,
            'amount' => 6.00, // 28 - 22
        ]);
    }

    public function test_subagent_cannot_verify_another_sellers_order(): void
    {
        $otherSubagent = Subagent::create([
            'agent_id' => $this->agent->id,
            'name' => 'Other Sub',
            'phone' => '0240000002',
            'email' => 'other@ex.com',
            'username' => 'othersub',
            'slug' => 'othersub',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $foreign = $otherSubagent->orders()->create([
            'reference' => 'DS-FOREIGN',
            'idempotency_key' => 'DS-FOREIGN',
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_AWAITING,
            'gateway' => 'paystack',
            'network' => 'mtn',
            'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 35,
            'seller_cost' => 28,
            'agent_cost' => 22,
            'base_cost' => 18,
            'status' => Order::STATUS_PENDING,
        ]);

        $this->actingAs($this->subagent, 'subagent')
            ->post(route('subagent.orders.verify-payment', ['order' => $foreign->id]))
            ->assertNotFound();
    }

    public function test_subagent_cannot_verify_already_paid_order(): void
    {
        $paidOrder = $this->subagent->orders()->create([
            'reference' => 'DS-ALREADY-PAID',
            'idempotency_key' => 'DS-ALREADY-PAID',
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_PAID,
            'network' => 'mtn',
            'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 35,
            'seller_cost' => 28,
            'agent_cost' => 22,
            'base_cost' => 18,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $this->actingAs($this->subagent, 'subagent')
            ->post(route('subagent.orders.verify-payment', ['order' => $paidOrder->id]))
            ->assertRedirect();

        // Stays paid and completed without duplicate actions
        $this->assertSame(Order::PAYMENT_PAID, $paidOrder->fresh()->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $paidOrder->fresh()->status);
    }

    public function test_subagent_can_retry_their_own_failed_order(): void
    {
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        Http::fake([
            'dbh.test/api/create_order' => Http::response([
                'success' => true,
                'data' => [
                    'requestId' => 46,
                    'orderStatus' => 'completed',
                    'price' => 18.0,
                    'transactionReference' => 'DBH-RETRY-TX-1',
                ],
            ]),
        ]);

        $failedOrder = $this->subagent->orders()->create([
            'reference' => 'DS-FAILED-1',
            'idempotency_key' => 'DS-FAILED-1',
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_PAID,
            'network' => 'mtn',
            'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 35,
            'seller_cost' => 28,
            'agent_cost' => 22,
            'base_cost' => 18,
            'status' => Order::STATUS_FAILED,
        ]);

        $response = $this->actingAs($this->subagent, 'subagent')
            ->post(route('subagent.orders.retry', ['order' => $failedOrder->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $failedOrder->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $failedOrder->status);
    }

    public function test_subagent_cannot_retry_completed_order(): void
    {
        $completedOrder = $this->subagent->orders()->create([
            'reference' => 'DS-DONE-1',
            'idempotency_key' => 'DS-DONE-1',
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_PAID,
            'network' => 'mtn',
            'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 35,
            'seller_cost' => 28,
            'agent_cost' => 22,
            'base_cost' => 18,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $this->actingAs($this->subagent, 'subagent')
            ->post(route('subagent.orders.retry', ['order' => $completedOrder->id]))
            ->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $completedOrder->fresh()->status);
    }
}

