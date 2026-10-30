<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BalanceTopup;
use App\Models\DbhConfig;
use App\Models\Earning;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Subagent;
use App\Services\Payments\PaymentVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifyPendingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::PAYSTACK, 'is_active' => true, 'public_key' => 'pk_test',
            'secret_key' => 'sk_test', 'currency' => 'GHS', 'min_topup' => 10, 'max_topup' => 10000, 'charge_percent' => 3,
        ]);
    }

    private function storefrontOrder(string $ref, int $hoursOld, string $payment = Order::PAYMENT_AWAITING): Order
    {
        $this->seq++;
        $agent = Agent::create(['name' => 'Agent', 'phone' => '05530'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret', 'is_active' => true]);
        $subagent = Subagent::create([
            'name' => 'Sub', 'phone' => '05531'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT), 'password' => 'secret',
            'agent_id' => $agent->id, 'is_active' => true,
        ]);

        $order = $subagent->orders()->create([
            'reference' => $ref, 'source' => Order::SOURCE_STOREFRONT, 'payment_status' => $payment,
            'gateway' => PaymentGateway::PAYSTACK, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0559999999', 'channel' => Order::CHANNEL_ONLINE,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_PENDING,
        ]);

        return $this->age($order, $hoursOld);
    }

    private function pendingTopup(string $ref, int $hoursOld, string $status = BalanceTopup::STATUS_PENDING): BalanceTopup
    {
        $agent = Agent::factory()->create();
        $topup = BalanceTopup::query()->create([
            'wallet_id' => $agent->walletOrCreate()->id, 'reference' => $ref, 'gateway' => PaymentGateway::PAYSTACK,
            'amount' => 50, 'charged_amount' => 51.50, 'currency' => 'GHS', 'status' => $status,
        ]);

        return $this->age($topup, $hoursOld);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  T  $model
     * @return T
     */
    private function age($model, int $hours)
    {
        $model->newQuery()->whereKey($model->getKey())->update(['created_at' => now()->subHours($hours)->subMinutes(5)]);

        return $model->refresh();
    }

    /** Swap in a verifier that always answers $result and counts how often it was asked. */
    private function fakeVerifier(string $result): object
    {
        $fake = new class($result) extends PaymentVerifier
        {
            public int $calls = 0;

            public function __construct(private string $result) {}

            public function verify(Order $order): string
            {
                $this->calls++;

                return $this->result;
            }
        };
        $this->app->instance(PaymentVerifier::class, $fake);

        return $fake;
    }

    private function fakeDbhAccepts(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 41, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
    }

    private function fakePaystack(string $status, string $reference): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => $status, 'amount' => 5150, 'currency' => 'GHS', 'reference' => $reference],
        ])]);
    }

    public function test_paid_order_is_dispatched(): void
    {
        $this->fakeVerifier(PaymentVerifier::PAID);
        $this->fakeDbhAccepts();
        $order = $this->storefrontOrder('DS-SWP001', 1);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
    }

    public function test_gateway_failed_order_is_marked_failed(): void
    {
        $this->fakeVerifier(PaymentVerifier::FAILED);
        $order = $this->storefrontOrder('DS-SWP002', 1);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(Order::PAYMENT_FAILED, $order->refresh()->payment_status);
    }

    public function test_unconfirmed_order_inside_window_stays_awaiting(): void
    {
        $this->fakeVerifier(PaymentVerifier::PENDING);
        $order = $this->storefrontOrder('DS-SWP003', 23);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(Order::PAYMENT_AWAITING, $order->refresh()->payment_status);
    }

    public function test_unconfirmed_order_past_window_gets_a_final_check_then_expires(): void
    {
        $verifier = $this->fakeVerifier(PaymentVerifier::PENDING);
        Http::fake();
        $order = $this->storefrontOrder('DS-SWP004', 24);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $order->refresh();
        $this->assertSame(1, $verifier->calls);
        $this->assertSame(Order::PAYMENT_EXPIRED, $order->payment_status);
        $this->assertSame(Order::STATUS_PENDING, $order->status); // only the payment changes
        $this->assertSame(0, Earning::count());
        Http::assertNothingSent();
    }

    public function test_order_paid_at_the_final_check_is_dispatched_not_expired(): void
    {
        $this->fakeVerifier(PaymentVerifier::PAID);
        $this->fakeDbhAccepts();
        $order = $this->storefrontOrder('DS-SWP005', 30);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(Order::PAYMENT_PAID, $order->refresh()->payment_status);
    }

    public function test_expiry_window_follows_config(): void
    {
        config(['payments.expiry_hours' => 2]);
        $this->fakeVerifier(PaymentVerifier::PENDING);
        $order = $this->storefrontOrder('DS-SWP006', 3);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(Order::PAYMENT_EXPIRED, $order->refresh()->payment_status);
    }

    public function test_prepaid_wallet_orders_are_never_touched(): void
    {
        $verifier = $this->fakeVerifier(PaymentVerifier::FAILED);
        $order = $this->storefrontOrder('DS-SWP007', 48);
        $order->update(['channel' => Order::CHANNEL_PREPAID, 'gateway' => null]);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(0, $verifier->calls);
        $this->assertSame(Order::PAYMENT_AWAITING, $order->refresh()->payment_status);
    }

    public function test_failed_and_expired_orders_are_not_swept_again(): void
    {
        $verifier = $this->fakeVerifier(PaymentVerifier::PAID);
        $this->storefrontOrder('DS-SWP008', 1, Order::PAYMENT_FAILED);
        $this->storefrontOrder('DS-SWP009', 30, Order::PAYMENT_EXPIRED);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(0, $verifier->calls);
    }

    public function test_admin_verify_dispatches_an_expired_order_the_gateway_confirms(): void
    {
        $this->actingAsAdmin();
        $this->fakeVerifier(PaymentVerifier::PAID);
        $this->fakeDbhAccepts();
        $order = $this->storefrontOrder('DS-SWP010', 30, Order::PAYMENT_EXPIRED);

        $this->post("/admin/orders/{$order->id}/verify-payment")->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
    }

    public function test_admin_verify_dispatches_a_failed_order_the_gateway_confirms(): void
    {
        $this->actingAsAdmin();
        $this->fakeVerifier(PaymentVerifier::PAID);
        $this->fakeDbhAccepts();
        $order = $this->storefrontOrder('DS-SWP011', 2, Order::PAYMENT_FAILED);

        $this->post("/admin/orders/{$order->id}/verify-payment")->assertRedirect();

        $this->assertSame(Order::PAYMENT_PAID, $order->refresh()->payment_status);
    }

    public function test_admin_verify_leaves_an_unconfirmed_expired_order_expired(): void
    {
        $this->actingAsAdmin();
        $this->fakeVerifier(PaymentVerifier::PENDING);
        Http::fake();
        $order = $this->storefrontOrder('DS-SWP012', 30, Order::PAYMENT_EXPIRED);

        $this->post("/admin/orders/{$order->id}/verify-payment")->assertRedirect();

        $this->assertSame(Order::PAYMENT_EXPIRED, $order->refresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_bulk_retry_cannot_dispatch_an_expired_order(): void
    {
        $this->actingAsAdmin();
        Http::fake();
        $order = $this->storefrontOrder('DS-SWP013', 30, Order::PAYMENT_EXPIRED);

        $this->post('/admin/orders/bulk', ['action' => 'retry', 'ids' => [$order->id]])->assertRedirect();

        $this->assertSame(Order::PAYMENT_EXPIRED, $order->refresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_paid_topup_is_credited(): void
    {
        $this->fakePaystack('success', 'TOP-SWP1');
        $topup = $this->pendingTopup('TOP-SWP1', 1);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->refresh()->status);
        $this->assertSame(50.0, (float) $topup->wallet->balance);
    }

    public function test_abandoned_topup_waits_inside_window_then_expires(): void
    {
        $this->fakePaystack('abandoned', 'TOP-SWP3');
        $fresh = $this->pendingTopup('TOP-SWP2', 3);
        $stale = $this->pendingTopup('TOP-SWP3', 24);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(BalanceTopup::STATUS_PENDING, $fresh->refresh()->status);
        $this->assertSame(BalanceTopup::STATUS_EXPIRED, $stale->refresh()->status);
        $this->assertSame(0.0, (float) $stale->wallet->balance);
    }

    public function test_admin_verify_credits_an_expired_topup_the_gateway_confirms(): void
    {
        $this->actingAsAdmin();
        $this->fakePaystack('success', 'TOP-SWP4');
        $topup = $this->pendingTopup('TOP-SWP4', 30, BalanceTopup::STATUS_EXPIRED);

        $this->post("/admin/topups/{$topup->id}/verify-payment")->assertRedirect();

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->refresh()->status);
        $this->assertSame(50.0, (float) $topup->wallet->balance);
    }
}
