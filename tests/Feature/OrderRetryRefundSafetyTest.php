<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Order;
use App\Models\Subagent;
use App\Services\Orders\OrderDispatchService;
use App\Services\Payments\PaymentVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Retry and refund read the real money source (wallet ledger / payment gateway), never just the
 * payment_status flag, so neither can send a bundle for free nor return money twice.
 */
class OrderRetryRefundSafetyTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
    }

    private function agent(): Agent
    {
        $this->seq++;
        $agent = Agent::create(['name' => 'Ag', 'phone' => '05540'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret', 'is_active' => true]);
        $agent->walletOrCreate()->credit(100, 'topup');

        return $agent;
    }

    /** A wallet order that really had GHS 20 taken at purchase. */
    private function walletOrder(Agent $agent, string $ref, string $status, string $payment): Order
    {
        $order = $agent->orders()->create([
            'reference' => $ref, 'source' => Order::SOURCE_PORTAL, 'payment_status' => $payment,
            'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0559999999', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 20, 'agent_cost' => 20, 'base_cost' => 15, 'status' => $status,
        ]);
        $agent->walletOrCreate()->debit(20, OrderDispatchService::TXN_PURCHASE, $ref);

        return $order;
    }

    private function unpaidStorefrontOrder(string $ref): Order
    {
        $agent = $this->agent();
        $subagent = Subagent::create([
            'name' => 'Sub', 'phone' => '05541'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret',
            'agent_id' => $agent->id, 'is_active' => true,
        ]);

        return $subagent->orders()->create([
            'reference' => $ref, 'source' => Order::SOURCE_STOREFRONT, 'payment_status' => Order::PAYMENT_AWAITING,
            'gateway' => 'paystack', 'network' => 'mtn', 'capacity_gb' => 5, 'beneficiary_phone' => '0559999999',
            'channel' => Order::CHANNEL_ONLINE, 'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20,
            'base_cost' => 15, 'status' => Order::STATUS_FAILED,
        ]);
    }

    private function fakeVerifier(string $result): void
    {
        $this->app->instance(PaymentVerifier::class, new class($result) extends PaymentVerifier
        {
            public function __construct(private string $result) {}

            public function verify(Order $order): string
            {
                return $this->result;
            }
        });
    }

    private function fakeDbhAccepts(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 90, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
    }

    private function balance(Agent $agent): float
    {
        return (float) $agent->walletOrCreate()->fresh()->balance;
    }

    public function test_retry_does_not_charge_again_when_the_ledger_shows_money_still_taken(): void
    {
        $this->fakeDbhAccepts();
        $agent = $this->agent();
        // Flag wrongly says unpaid, but the ledger shows the GHS 20 is still taken.
        $order = $this->walletOrder($agent, 'DS-RS001', Order::STATUS_FAILED, Order::PAYMENT_AWAITING);

        $this->post('/admin/orders/bulk', ['action' => 'retry', 'ids' => [$order->id]])->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $order->refresh()->status);
        $this->assertSame(80.0, $this->balance($agent));
    }

    public function test_retry_charges_again_when_the_ledger_shows_money_was_refunded(): void
    {
        $this->fakeDbhAccepts();
        $agent = $this->agent();
        // Flag wrongly says paid, but the ledger shows the GHS 20 already went back.
        $order = $this->walletOrder($agent, 'DS-RS002', Order::STATUS_FAILED, Order::PAYMENT_PAID);
        $agent->walletOrCreate()->credit(20, 'order_refund', 'DS-RS002');

        $this->post('/admin/orders/bulk', ['action' => 'retry', 'ids' => [$order->id]])->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $order->refresh()->status);
        $this->assertSame(80.0, $this->balance($agent));
    }

    public function test_retry_does_not_send_an_unpaid_storefront_order_the_gateway_has_not_confirmed(): void
    {
        $this->fakeVerifier(PaymentVerifier::PENDING);
        Http::fake();
        $order = $this->unpaidStorefrontOrder('DS-RS003');

        $this->post('/admin/orders/bulk', ['action' => 'retry', 'ids' => [$order->id]])->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertSame(Order::PAYMENT_AWAITING, $order->payment_status);
        Http::assertNothingSent();
    }

    public function test_retry_sends_an_unpaid_storefront_order_once_the_gateway_confirms_it(): void
    {
        $this->fakeVerifier(PaymentVerifier::PAID);
        $this->fakeDbhAccepts();
        $order = $this->unpaidStorefrontOrder('DS-RS004');

        $this->post('/admin/orders/bulk', ['action' => 'retry', 'ids' => [$order->id]])->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
    }

    public function test_refund_button_returns_money_and_marks_the_payment_not_paid(): void
    {
        $agent = $this->agent();
        $order = $this->walletOrder($agent, 'DS-RS005', Order::STATUS_COMPLETED, Order::PAYMENT_PAID);

        $this->post("/admin/orders/{$order->id}/refund")->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::STATUS_REFUNDED, $order->status);
        $this->assertSame(Order::PAYMENT_AWAITING, $order->payment_status);
        $this->assertSame(100.0, $this->balance($agent));
    }

    public function test_marking_a_refunded_order_failed_does_not_refund_it_again(): void
    {
        $agent = $this->agent();
        $order = $this->walletOrder($agent, 'DS-RS006', Order::STATUS_COMPLETED, Order::PAYMENT_PAID);
        $this->post("/admin/orders/{$order->id}/refund")->assertRedirect();

        $this->post('/admin/orders/bulk', ['action' => 'apply-status', 'status' => 'failed', 'ids' => [$order->id]])->assertRedirect();

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->assertSame(100.0, $this->balance($agent)); // refunded once, not twice
    }

    public function test_failing_an_order_never_returns_more_than_the_ledger_holds(): void
    {
        $agent = $this->agent();
        $order = $this->walletOrder($agent, 'DS-RS007', Order::STATUS_PROCESSING, Order::PAYMENT_PAID);
        $agent->walletOrCreate()->credit(20, 'order_refund', 'DS-RS007'); // money already went back

        $this->post('/admin/orders/bulk', ['action' => 'apply-status', 'status' => 'failed', 'ids' => [$order->id]])->assertRedirect();

        $this->assertSame(Order::STATUS_FAILED, $order->refresh()->status);
        $this->assertSame(100.0, $this->balance($agent));
    }
}
