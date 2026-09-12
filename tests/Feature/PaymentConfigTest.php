<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loads(): void
    {
        $this->get('/admin/payment-config')->assertOk();
    }

    public function test_paystack_config_saves_and_encrypts_secret(): void
    {
        $this->put('/admin/payment-config/paystack', [
            'public_key' => 'pk_test_123',
            'secret_key' => 'sk_test_secret',
            'webhook_secret' => 'whsec_1',
            'is_active' => true,
            'is_live' => false,
            'currency' => 'GHS',
            'min_topup' => 10,
            'max_topup' => 5000,
            'charge_percent' => 1.95,
        ])->assertRedirect();

        $row = PaymentGateway::forGateway(PaymentGateway::PAYSTACK);
        $this->assertNotNull($row);
        $this->assertSame('sk_test_secret', $row->secret_key);          // decrypts back
        $this->assertTrue($row->is_active);

        // Stored ciphertext is not the plaintext.
        $raw = (string) DB::table('payment_gateways')->where('gateway', 'paystack')->value('secret_key');
        $this->assertNotSame('sk_test_secret', $raw);
    }

    public function test_blank_secret_keeps_existing(): void
    {
        PaymentGateway::create([
            'gateway' => PaymentGateway::PAYSTACK, 'public_key' => 'pk', 'secret_key' => 'sk_keep',
            'is_active' => true, 'currency' => 'GHS', 'min_topup' => 10, 'max_topup' => 5000, 'charge_percent' => 0,
        ]);

        $this->put('/admin/payment-config/paystack', [
            'public_key' => 'pk_updated', 'secret_key' => '', 'webhook_secret' => '',
            'is_active' => true, 'is_live' => false, 'currency' => 'GHS',
            'min_topup' => 10, 'max_topup' => 5000, 'charge_percent' => 0,
        ])->assertRedirect();

        $row = PaymentGateway::forGateway(PaymentGateway::PAYSTACK);
        $this->assertSame('pk_updated', $row->public_key);
        $this->assertSame('sk_keep', $row->secret_key); // untouched
    }

    public function test_moolre_requires_credentials_when_active(): void
    {
        $this->put('/admin/payment-config/moolre', [
            'is_active' => true, 'currency' => 'GHS',
            'public_key' => '', 'moolre_username' => '', 'moolre_account_number' => '',
        ])->assertSessionHasErrors(['public_key', 'moolre_username', 'moolre_account_number']);
    }

    public function test_max_topup_must_be_gte_min(): void
    {
        $this->put('/admin/payment-config/paystack', [
            'public_key' => 'pk', 'secret_key' => 'sk', 'is_active' => true, 'is_live' => false,
            'currency' => 'GHS', 'min_topup' => 100, 'max_topup' => 50, 'charge_percent' => 0,
        ])->assertSessionHasErrors('max_topup');
    }

    public function test_resolver_routes_by_amount_and_context(): void
    {
        PaymentGateway::create([
            'gateway' => PaymentGateway::PAYSTACK, 'public_key' => 'pk', 'secret_key' => 'sk',
            'is_active' => true, 'currency' => 'GHS', 'min_topup' => 10, 'max_topup' => 5000, 'charge_percent' => 0,
        ]);
        PaymentGateway::create([
            'gateway' => PaymentGateway::MOOLRE, 'public_key' => 'mpk', 'moolre_username' => 'u', 'moolre_account_number' => '123',
            'is_active' => true, 'currency' => 'GHS', 'min_topup' => 10, 'max_topup' => 100000, 'charge_percent' => 0,
        ]);

        $resolver = app(PaymentGatewayResolver::class);
        $this->assertSame(PaymentGateway::PAYSTACK, $resolver->resolveForAgentTopup(1499));
        $this->assertSame(PaymentGateway::MOOLRE, $resolver->resolveForAgentTopup(1500));
        $this->assertSame(PaymentGateway::PAYSTACK, $resolver->resolveForPublicCheckout());
    }

    public function test_resolver_falls_back_when_preferred_unusable(): void
    {
        // Only Moolre usable → a small top-up (would prefer Paystack) falls back to Moolre.
        PaymentGateway::create([
            'gateway' => PaymentGateway::MOOLRE, 'public_key' => 'mpk', 'moolre_username' => 'u', 'moolre_account_number' => '123',
            'is_active' => true, 'currency' => 'GHS', 'min_topup' => 10, 'max_topup' => 100000, 'charge_percent' => 0,
        ]);

        $this->assertSame(PaymentGateway::MOOLRE, app(PaymentGatewayResolver::class)->resolveForAgentTopup(50));
    }

    public function test_charge_percent_fee_math_roundtrips(): void
    {
        $row = new PaymentGateway(['charge_percent' => 2.0]);
        $gross = $row->grossFromBase(100);
        $this->assertSame(102.0, $gross);
        $this->assertSame(100.0, $row->baseFromGross($gross));
    }
}
