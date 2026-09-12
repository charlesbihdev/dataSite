<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use App\Services\Payments\PaymentVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifyPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
    }

    private int $seq = 0;

    private function awaitingOrder(string $ref): Order
    {
        $this->seq++;
        $agent = Agent::create(['name' => 'Agent', 'phone' => '05510'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret', 'is_active' => true]);
        $subagent = Subagent::create([
            'name' => 'Sub', 'phone' => '05511'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret',
            'agent_id' => $agent->id, 'is_active' => true,
        ]);

        return $subagent->orders()->create([
            'reference' => $ref,
            'source' => Order::SOURCE_STOREFRONT,
            'payment_status' => Order::PAYMENT_AWAITING,
            'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0559999999',
            'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    private function fakeVerifier(string $result): void
    {
        $this->app->instance(PaymentVerifier::class, new class($result) extends PaymentVerifier {
            public function __construct(private string $result) {}

            public function verify(Order $order): string
            {
                return $this->result;
            }
        });
    }

    public function test_verified_paid_order_is_dispatched(): void
    {
        $this->fakeVerifier(PaymentVerifier::PAID);
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 40, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
        $order = $this->awaitingOrder('DS-VER0001');

        $this->post("/admin/orders/{$order->id}/verify-payment")->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(2, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_failed_payment_marks_order_failed_and_sends_nothing(): void
    {
        $this->fakeVerifier(PaymentVerifier::FAILED);
        Http::fake();
        $order = $this->awaitingOrder('DS-VER0002');

        $this->post("/admin/orders/{$order->id}/verify-payment")->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_FAILED, $order->payment_status);
        $this->assertSame(Order::STATUS_PENDING, $order->status); // never dispatched
        $this->assertSame(0, Earning::count());
        Http::assertNothingSent();
    }

    public function test_pending_result_leaves_order_awaiting(): void
    {
        // No verifier binding → default PENDING (no gateway configured yet).
        Http::fake();
        $order = $this->awaitingOrder('DS-VER0003');

        $this->post("/admin/orders/{$order->id}/verify-payment")->assertRedirect();

        $this->assertSame(Order::PAYMENT_AWAITING, $order->refresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_order_pages_load_and_index_redirects(): void
    {
        $this->get('/admin/orders/agent')->assertOk();
        $this->get('/admin/orders/regular')->assertOk();
        $this->get('/admin/orders')->assertRedirect('/admin/orders/agent');
    }

    public function test_mark_verified_confirms_and_dispatches(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 50, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
        $order = $this->awaitingOrder('DS-MV0001');

        $this->post("/admin/orders/{$order->id}/mark-verified")->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(2, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_awaiting_order_can_be_deleted(): void
    {
        $order = $this->awaitingOrder('DS-DEL0001');

        $this->delete("/admin/orders/{$order->id}")->assertRedirect();

        $this->assertNull(Order::find($order->id));
    }

    public function test_paid_order_cannot_be_deleted(): void
    {
        $order = $this->awaitingOrder('DS-DEL0002');
        $order->update(['payment_status' => Order::PAYMENT_PAID]);

        $this->delete("/admin/orders/{$order->id}")->assertRedirect();

        $this->assertNotNull(Order::find($order->id)); // guard held
    }

    public function test_bulk_mark_verified_dispatches_all_selected(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 60, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
        $a = $this->awaitingOrder('DS-BULK001');
        $b = $this->awaitingOrder('DS-BULK002');

        $this->post('/admin/orders/bulk', ['action' => 'mark-verified', 'ids' => [$a->id, $b->id]])->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $a->refresh()->status);
        $this->assertSame(Order::STATUS_COMPLETED, $b->refresh()->status);
        $this->assertSame(0, Order::where('payment_status', Order::PAYMENT_AWAITING)->count());
    }

    public function test_bulk_delete_removes_only_awaiting_selected(): void
    {
        $a = $this->awaitingOrder('DS-BULK003');
        $paid = $this->awaitingOrder('DS-BULK004');
        $paid->update(['payment_status' => Order::PAYMENT_PAID]);

        $this->post('/admin/orders/bulk', ['action' => 'delete', 'ids' => [$a->id, $paid->id]])->assertRedirect();

        $this->assertNull(Order::find($a->id));      // awaiting → deleted
        $this->assertNotNull(Order::find($paid->id)); // paid → protected
    }

    public function test_agent_export_streams_csv(): void
    {
        $agent = Agent::create(['name' => 'Exp', 'phone' => '0559990000', 'password' => 'secret', 'is_active' => true]);
        $agent->orders()->create([
            'reference' => 'DS-EXP0001',
            'source' => Order::SOURCE_PORTAL,
            'payment_status' => Order::PAYMENT_PAID,
            'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0559999999',
            'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 20, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $res = $this->get('/admin/orders/export?segment=agent');

        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('DS-EXP0001', $res->streamedContent());
    }

    public function test_bulk_retry_redebits_and_redispatches_failed_order(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 70, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
        $this->seq++;
        $agent = Agent::create(['name' => 'Ag', 'phone' => '05520'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret', 'is_active' => true]);
        $agent->walletOrCreate()->credit(100, 'topup');

        // A failed prepaid order whose money was already refunded → payment no longer held.
        $order = $agent->orders()->create([
            'reference' => 'DS-RTY0001', 'source' => Order::SOURCE_PORTAL, 'payment_status' => Order::PAYMENT_AWAITING,
            'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0559999999', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 20, 'agent_cost' => 20, 'base_cost' => 15, 'status' => Order::STATUS_FAILED,
        ]);

        $this->post('/admin/orders/bulk', ['action' => 'retry', 'ids' => [$order->id]])->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(80.0, (float) $agent->walletOrCreate()->balance); // re-debited 20
        $this->assertSame(1, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_bulk_apply_status_completed_credits_earnings(): void
    {
        $this->seq++;
        $agent = Agent::create(['name' => 'Ag', 'phone' => '05521'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret', 'is_active' => true]);
        $order = $agent->orders()->create([
            'reference' => 'DS-APS0001', 'source' => Order::SOURCE_PORTAL, 'payment_status' => Order::PAYMENT_PAID,
            'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0559999999', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 20, 'agent_cost' => 20, 'base_cost' => 15, 'status' => Order::STATUS_PROCESSING,
        ]);
        $agent->earnings()->create(['order_id' => $order->id, 'type' => Earning::TYPE_SHOP_PROFIT, 'amount' => 10, 'status' => Earning::STATUS_PENDING]);

        $this->post('/admin/orders/bulk', ['action' => 'apply-status', 'status' => 'completed', 'ids' => [$order->id]])->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $order->refresh()->status);
        $this->assertSame(1, Earning::where('status', Earning::STATUS_CREDITED)->count());
    }

    public function test_bulk_apply_status_plain_transition(): void
    {
        $order = $this->awaitingOrder('DS-APS0002');
        $order->update(['status' => Order::STATUS_PENDING]);

        $this->post('/admin/orders/bulk', ['action' => 'apply-status', 'status' => 'processing', 'ids' => [$order->id]])->assertRedirect();

        $this->assertSame(Order::STATUS_PROCESSING, $order->refresh()->status);
    }

    public function test_bulk_sync_queues_processing_orders(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $order = $this->awaitingOrder('DS-BULK005');
        $order->update(['status' => Order::STATUS_PROCESSING, 'upstream_request_id' => '99']);

        $this->post('/admin/orders/bulk', ['action' => 'sync', 'ids' => [$order->id]])->assertRedirect();

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PollUpstreamOrderStatus::class, 1);
    }
}
