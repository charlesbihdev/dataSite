<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BalanceTopup;
use App\Models\PaymentGateway;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WalletTopupTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    private Wallet $wallet;

    private const SECRET = 'sk_test_secret';

    private const MOOLRE_SECRET = 'moolre_shared_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::PAYSTACK,
            'is_active' => true,
            'public_key' => 'pk_test_public',
            'secret_key' => self::SECRET,
            'currency' => 'GHS',
            'min_topup' => 10,
            'max_topup' => 10000,
            'charge_percent' => 0,
        ]);

        $this->agent = Agent::factory()->create();
        $this->wallet = $this->agent->walletOrCreate();
        $this->actingAs($this->agent, 'agent');
    }

    public function test_store_opens_gateway_checkout_and_records_a_pending_topup(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'PSTK_ref'],
            ]),
        ]);

        $this->post(route('agent.topup'), ['amount' => 100])
            ->assertRedirect('https://checkout.paystack.com/xyz');

        $topup = BalanceTopup::query()->firstOrFail();
        $this->assertSame(BalanceTopup::STATUS_PENDING, $topup->status);
        $this->assertEquals(100, (float) $topup->amount);
        $this->assertEquals(0, (float) $this->wallet->fresh()->balance);
    }

    public function test_store_rejects_amount_below_the_gateway_minimum(): void
    {
        Http::fake();

        $this->post(route('agent.topup'), ['amount' => 5])->assertRedirect();

        $this->assertSame(0, BalanceTopup::query()->count());
        Http::assertNothingSent();
    }

    public function test_callback_verifies_and_credits_the_wallet(): void
    {
        $topup = $this->pendingTopup();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => $this->successTx($topup),
            ]),
        ]);

        $this->get(route('agent.topup.callback', ['reference' => $topup->reference]))
            ->assertRedirect(route('agent.transactions'));

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->fresh()->status);
        $this->assertEquals(100, (float) $this->wallet->fresh()->balance);
    }

    public function test_callback_marks_failed_when_the_gateway_reports_failure(): void
    {
        $topup = $this->pendingTopup();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'failed', 'reference' => $topup->reference],
            ]),
        ]);

        $this->get(route('agent.topup.callback', ['reference' => $topup->reference]))->assertRedirect();

        $this->assertSame(BalanceTopup::STATUS_FAILED, $topup->fresh()->status);
        $this->assertEquals(0, (float) $this->wallet->fresh()->balance);
    }

    public function test_webhook_credits_the_wallet_exactly_once(): void
    {
        $topup = $this->pendingTopup();
        $payload = json_encode(['event' => 'charge.success', 'data' => $this->successTx($topup)]);

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk(); // replay

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->fresh()->status);
        $this->assertEquals(100, (float) $this->wallet->fresh()->balance);
    }

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        $topup = $this->pendingTopup();
        $payload = json_encode(['event' => 'charge.success', 'data' => $this->successTx($topup)]);

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => 'not-a-valid-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(401);

        $this->assertSame(BalanceTopup::STATUS_PENDING, $topup->fresh()->status);
        $this->assertEquals(0, (float) $this->wallet->fresh()->balance);
    }

    public function test_store_opens_a_moolre_checkout_when_moolre_is_the_usable_gateway(): void
    {
        $this->configureMoolreOnly();

        Http::fake([
            'api.moolre.com/embed/link' => Http::response([
                'status' => 1,
                'data' => ['authorization_url' => 'https://pay.moolre.com/abc'],
            ]),
        ]);

        $this->post(route('agent.topup'), ['amount' => 100])
            ->assertRedirect('https://pay.moolre.com/abc');

        $topup = BalanceTopup::query()->firstOrFail();
        $this->assertSame(PaymentGateway::MOOLRE, $topup->gateway);
        $this->assertSame(BalanceTopup::STATUS_PENDING, $topup->status);
    }

    public function test_moolre_webhook_reverifies_via_status_and_credits_exactly_once(): void
    {
        $this->configureMoolreOnly();
        $topup = $this->pendingTopup(PaymentGateway::MOOLRE);

        Http::fake([
            'api.moolre.com/open/transact/status' => Http::response([
                'status' => 1,
                'data' => ['txstatus' => 1, 'value' => 100, 'currency' => 'GHS'],
            ]),
        ]);

        $body = ['data' => ['secret' => self::MOOLRE_SECRET, 'externalref' => $topup->reference]];
        $this->postJson(route('webhooks.moolre'), $body)->assertOk();
        $this->postJson(route('webhooks.moolre'), $body)->assertOk(); // replay

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->fresh()->status);
        $this->assertEquals(100, (float) $this->wallet->fresh()->balance);
    }

    public function test_moolre_webhook_rejects_an_invalid_secret(): void
    {
        $this->configureMoolreOnly();
        $topup = $this->pendingTopup(PaymentGateway::MOOLRE);

        Http::fake();

        $this->postJson(route('webhooks.moolre'), [
            'data' => ['secret' => 'wrong-secret', 'externalref' => $topup->reference],
        ])->assertStatus(401);

        $this->assertSame(BalanceTopup::STATUS_PENDING, $topup->fresh()->status);
        $this->assertEquals(0, (float) $this->wallet->fresh()->balance);
        Http::assertNothingSent();
    }

    private function pendingTopup(string $gateway = PaymentGateway::PAYSTACK): BalanceTopup
    {
        return BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'gateway' => $gateway,
            'reference' => 'MTP_test_ref',
            'amount' => 100,
            'charged_amount' => 100,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);
    }

    /**
     * Configure Moolre as the only usable gateway so top-up routing lands on it deterministically.
     */
    private function configureMoolreOnly(): void
    {
        PaymentGateway::query()->where('gateway', PaymentGateway::PAYSTACK)->update(['is_active' => false]);

        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::MOOLRE,
            'is_active' => true,
            'public_key' => 'moolre_pub',
            'webhook_secret' => self::MOOLRE_SECRET,
            'moolre_username' => 'moolre_user',
            'moolre_account_number' => '10000123',
            'currency' => 'GHS',
            'min_topup' => 10,
            'max_topup' => 100000,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function successTx(BalanceTopup $topup): array
    {
        return [
            'status' => 'success',
            'amount' => 10000, // kobo — matches charged_amount 100.00
            'currency' => 'GHS',
            'reference' => $topup->reference,
            'metadata' => ['type' => 'wallet_topup', 'topup_id' => $topup->id],
        ];
    }

    private function postSignedWebhook(string $payload): TestResponse
    {
        $signature = hash_hmac('sha512', $payload, self::SECRET);

        return $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
