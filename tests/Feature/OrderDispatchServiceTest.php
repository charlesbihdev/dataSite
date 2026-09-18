<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use App\Services\Databundleshub\UpstreamClient;
use App\Services\Orders\NewOrderData;
use App\Services\Orders\OrderBulkService;
use App\Services\Orders\OrderDispatchService;
use App\Services\Orders\OrderSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
    }

    private function agent(): Agent
    {
        return Agent::create(['name' => 'Agent', 'phone' => '0551110000', 'password' => 'secret', 'is_active' => true]);
    }

    private function subagent(Agent $agent): Subagent
    {
        return Subagent::create([
            'name' => 'Sub', 'phone' => '0551110001', 'password' => 'secret',
            'agent_id' => $agent->id, 'is_active' => true,
        ]);
    }

    private function fund(Agent|Subagent $owner, float $amount): void
    {
        $owner->walletOrCreate()->credit($amount, 'topup');
    }

    private function fakeUpstream(array $body, int $status = 200): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response($body, $status)]);
    }

    public function test_subagent_sale_debits_wallet_and_credits_three_way_split_on_completion(): void
    {
        $agent = $this->agent();
        $subagent = $this->subagent($agent);
        $this->fund($subagent, 100);
        $this->fakeUpstream([
            'success' => true,
            'data' => ['requestId' => 7, 'orderStatus' => 'completed', 'price' => 15.0],
        ]);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $subagent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 25, agentCost: 20, baseCost: 15,
        ));

        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame('15.00', $order->upstream_cost);
        $this->assertSame(75.0, (float) $subagent->walletOrCreate()->balance);

        // Subagent retail markup = 30 - 25; agent commission = 25 - 20.
        $this->assertSame(5.0, (float) Earning::where('earner_type', $subagent->getMorphClass())
            ->where('type', Earning::TYPE_SHOP_PROFIT)->value('amount'));
        $this->assertSame(5.0, (float) Earning::where('earner_type', $agent->getMorphClass())
            ->where('type', Earning::TYPE_COMMISSION)->value('amount'));
        $this->assertSame(2, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_agent_direct_sale_credits_only_shop_profit(): void
    {
        $agent = $this->agent();
        $this->fund($agent, 100);
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 8, 'orderStatus' => 'completed', 'price' => 15.0]]);

        app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));

        $this->assertSame(1, Earning::count());
        $this->assertSame(10.0, (float) Earning::where('type', Earning::TYPE_SHOP_PROFIT)->value('amount'));
    }

    public function test_insufficient_balance_rolls_back_with_no_order(): void
    {
        $subagent = $this->subagent($this->agent());
        $this->fakeUpstream(['success' => true, 'data' => ['orderStatus' => 'completed']]);

        $this->expectException(InsufficientBalanceException::class);

        try {
            app(OrderDispatchService::class)->dispatch(new NewOrderData(
                seller: $subagent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
                customerPrice: 30, sellerCost: 25, agentCost: 20, baseCost: 15,
            ));
        } finally {
            $this->assertSame(0, Order::count());
            $this->assertSame(0, Earning::count());
            Http::assertNothingSent();
        }
    }

    public function test_upstream_rejection_reverses_wallet_and_earnings(): void
    {
        $agent = $this->agent();
        $subagent = $this->subagent($agent);
        $this->fund($subagent, 100);
        $this->fakeUpstream([
            'success' => false, 'error' => 'no stock', 'code' => 'OUT_OF_STOCK',
            'data' => ['orderStatus' => 'rejected'],
        ], 409);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $subagent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 25, agentCost: 20, baseCost: 15,
        ));

        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertSame(100.0, (float) $subagent->walletOrCreate()->balance); // debit refunded
        $this->assertSame(2, Earning::where('status', Earning::STATUS_REVERSED)->count());
        $this->assertSame(0, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_no_upstream_connection_holds_order_as_pending_without_reversing(): void
    {
        DbhConfig::query()->delete(); // no active connection → placeOrder throws before any HTTP
        $agent = $this->agent();
        $this->fund($agent, 100);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));

        // Held in the system, not failed: money stays reserved and earnings stay pending.
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertNull($order->upstream_request_id);
        // Seller-safe note only — the internal cause must never leak to failure_reason.
        $this->assertSame(OrderDispatchService::HELD_REASON, $order->failure_reason);
        $this->assertStringNotContainsStringIgnoringCase('databundleshub', (string) $order->failure_reason);
        $this->assertSame(80.0, (float) $agent->walletOrCreate()->balance); // debit NOT refunded
        $this->assertSame(1, Earning::where('status', Earning::STATUS_PENDING)->count());
        $this->assertSame(0, Earning::where('status', Earning::STATUS_REVERSED)->count());
    }

    public function test_held_pending_order_is_dispatched_when_admin_retries_after_connecting(): void
    {
        DbhConfig::query()->delete();
        $agent = $this->agent();
        $this->fund($agent, 100);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));
        $this->assertSame(Order::STATUS_PENDING, $order->status);

        // Admin connects Databundleshub and retries the held order in bulk.
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 40, 'orderStatus' => 'completed', 'price' => 15.0]]);

        app(OrderBulkService::class)->apply('retry', [$order->id]);

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(80.0, (float) $agent->walletOrCreate()->balance); // not re-debited
        $this->assertSame(1, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_poll_job_settles_a_processing_order_when_upstream_completes(): void
    {
        Queue::fake(); // capture the auto-dispatched poll job instead of running it synchronously
        $agent = $this->agent();
        $this->fund($agent, 100);
        // Leave the order processing by returning a non-terminal status from create_order.
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 9, 'orderStatus' => 'processing']]);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        Queue::assertPushed(PollUpstreamOrderStatus::class);

        Http::fake(['dbh.test/api/developer/purchase-status*' => Http::response([
            'success' => true, 'data' => ['requestId' => 9, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);

        (new PollUpstreamOrderStatus($order->id))->handle(
            app(UpstreamClient::class), app(OrderSettlementService::class)
        );

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(1, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_poll_settles_when_upstream_reports_delivered_order_status(): void
    {
        Queue::fake();
        $agent = $this->agent();
        $this->fund($agent, 100);
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 11, 'orderStatus' => 'processing']]);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);

        // Databundleshub's real shape: the fulfilled order row reads `delivered`, and the
        // request row reads `processingStatus: completed`. We must settle on this, not stall.
        Http::fake(['dbh.test/api/developer/purchase-status*' => Http::response([
            'success' => true,
            'data' => [
                'requestId' => 11,
                'orderStatus' => 'delivered',
                'processingStatus' => 'completed',
                'price' => 15.0,
                'completedAt' => now()->toIso8601String(),
            ],
        ])]);

        (new PollUpstreamOrderStatus($order->id))->handle(
            app(UpstreamClient::class), app(OrderSettlementService::class)
        );

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame('15.00', $order->fresh()->upstream_cost);
        $this->assertSame(1, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_admin_refund_of_completed_order_returns_deposit_and_reverses_earnings(): void
    {
        $agent = $this->agent();
        $this->fund($agent, 100);
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 20, 'orderStatus' => 'completed', 'price' => 15.0]]);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(80.0, (float) $agent->walletOrCreate()->balance); // 100 - 20 debit
        $this->assertSame(1, Earning::where('status', Earning::STATUS_CREDITED)->count());

        $result = app(OrderSettlementService::class)->refund($order, 'data never delivered');

        $this->assertTrue($result['refunded']);
        $this->assertSame(20.0, $result['amount']);
        $this->assertSame(Order::STATUS_REFUNDED, $order->fresh()->status);
        $this->assertSame('data never delivered', $order->fresh()->failure_reason);
        $this->assertSame(100.0, (float) $agent->walletOrCreate()->balance); // deposit returned
        $this->assertSame(0, Earning::where('status', Earning::STATUS_CREDITED)->count());
        $this->assertSame(1, Earning::where('status', Earning::STATUS_REVERSED)->count());
    }

    public function test_fulfill_paid_dispatches_an_awaiting_storefront_order(): void
    {
        $agent = $this->agent();
        $subagent = $this->subagent($agent);
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 30, 'orderStatus' => 'completed', 'price' => 15.0]]);

        // Storefront order: created awaiting, no wallet debit, no earnings yet — as the gateway
        // flow would leave it before an admin verifies the payment.
        $order = $subagent->orders()->create([
            'reference' => 'DS-STORE0001',
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_AWAITING,
            'network' => 'mtn',
            'capacity_gb' => 5,
            'beneficiary_phone' => '0559999999',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 30,
            'seller_cost' => 25,
            'agent_cost' => 20,
            'base_cost' => 15,
            'status' => Order::STATUS_PENDING,
        ]);
        $this->assertSame(0, Earning::count());

        $fulfilled = app(OrderDispatchService::class)->fulfillPaid($order->fresh());

        $this->assertSame(Order::STATUS_COMPLETED, $fulfilled->status);
        $this->assertSame(2, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_fulfill_paid_is_idempotent_and_does_not_resend(): void
    {
        $agent = $this->agent();
        $subagent = $this->subagent($agent);
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 31, 'orderStatus' => 'completed', 'price' => 15.0]]);

        $order = $subagent->orders()->create([
            'reference' => 'DS-STORE0002',
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_AWAITING,
            'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0559999999',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_PENDING,
        ]);

        $service = app(OrderDispatchService::class);
        $service->fulfillPaid($order->fresh());
        $service->fulfillPaid($order->fresh()); // second verify must be a no-op

        Http::assertSentCount(1);
        $this->assertSame(2, Earning::count()); // not doubled
    }

    public function test_refund_is_idempotent(): void
    {
        $agent = $this->agent();
        $this->fund($agent, 100);
        $this->fakeUpstream(['success' => true, 'data' => ['requestId' => 21, 'orderStatus' => 'completed', 'price' => 15.0]]);

        $order = app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));

        app(OrderSettlementService::class)->refund($order);
        $second = app(OrderSettlementService::class)->refund($order->fresh());

        $this->assertFalse($second['refunded']);
        $this->assertSame(100.0, (float) $agent->walletOrCreate()->balance); // not double-credited
    }
}
