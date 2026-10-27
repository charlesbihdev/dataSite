<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BalanceTopup;
use App\Models\PaymentGateway;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTopupsTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::PAYSTACK,
            'is_active' => true,
            'public_key' => 'pk_test_public',
            'secret_key' => 'sk_test_secret',
            'currency' => 'GHS',
            'min_topup' => 10,
            'max_topup' => 10000,
            'charge_percent' => 3,
        ]);

        PaymentGateway::query()->create([
            'gateway' => PaymentGateway::MOOLRE,
            'is_active' => true,
            'public_key' => 'moolre_user_id',
            'secret_key' => 'moolre_secret',
            'currency' => 'GHS',
            'min_topup' => 10,
            'max_topup' => 10000,
            'charge_percent' => 0,
        ]);

        $this->agent = Agent::factory()->create([
            'name' => 'Kwame Mensah',
            'phone' => '0244123456',
        ]);
        $this->wallet = $this->agent->walletOrCreate();
    }

    public function test_admin_can_view_topups_index_with_filters_and_stats(): void
    {
        $this->actingAsAdmin();

        BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-SUCCESS-1',
            'gateway' => 'paystack',
            'amount' => 100.00,
            'charged_amount' => 103.00,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_SUCCESS,
        ]);

        BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-PENDING-1',
            'gateway' => 'paystack',
            'amount' => 50.00,
            'charged_amount' => 51.50,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-FAILED-1',
            'gateway' => 'moolre',
            'amount' => 30.00,
            'charged_amount' => 30.00,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_FAILED,
        ]);

        $response = $this->get(route('admin.topups.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/topups')
                ->has('topups.data', 3)
                ->where('stats.total_count', 3)
                ->where('stats.completed_count', 1)
                ->where('stats.pending_count', 1)
                ->where('stats.failed_count', 1)
                ->where('stats.total_volume', fn ($v) => (float) $v === 100.0)
            );

        // Filter by status
        $responsePending = $this->get(route('admin.topups.index', ['status' => 'pending']));
        $responsePending->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('topups.data', 1)
                ->where('topups.data.0.reference', 'TOP-PENDING-1')
            );

        // Filter by gateway
        $responseMoolre = $this->get(route('admin.topups.index', ['gateway' => 'moolre']));
        $responseMoolre->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('topups.data', 1)
                ->where('topups.data.0.reference', 'TOP-FAILED-1')
            );

        // Search by reference
        $responseSearch = $this->get(route('admin.topups.index', ['q' => 'SUCCESS-1']));
        $responseSearch->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('topups.data', 1)
                ->where('topups.data.0.reference', 'TOP-SUCCESS-1')
            );
    }

    public function test_admin_reverify_credits_wallet_on_paystack_success(): void
    {
        $this->actingAsAdmin();

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-REVERIFY-PSTK',
            'gateway' => 'paystack',
            'amount' => 100.00,
            'charged_amount' => 103.00,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 999888,
                    'status' => 'success',
                    'reference' => 'TOP-REVERIFY-PSTK',
                    'amount' => 10300,
                    'currency' => 'GHS',
                    'channel' => 'mobile_money',
                ],
            ]),
        ]);

        $response = $this->post(route('admin.topups.verify-payment', ['topup' => $topup->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->fresh()->status);
        $this->assertEquals(100.00, (float) $this->wallet->fresh()->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->wallet->id,
            'type' => 'topup',
            'amount' => 100.00,
        ]);
    }

    public function test_admin_reverify_marks_topup_failed_when_gateway_reports_failed(): void
    {
        $this->actingAsAdmin();

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-FAILED-PSTK',
            'gateway' => 'paystack',
            'amount' => 75.00,
            'charged_amount' => 77.25,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 999889,
                    'status' => 'failed',
                    'reference' => 'TOP-FAILED-PSTK',
                    'amount' => 7725,
                    'currency' => 'GHS',
                    'gateway_response' => 'Insufficient funds',
                ],
            ]),
        ]);

        $response = $this->post(route('admin.topups.verify-payment', ['topup' => $topup->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(BalanceTopup::STATUS_FAILED, $topup->fresh()->status);
        $this->assertEquals(0.00, (float) $this->wallet->fresh()->balance);
    }

    public function test_reverify_credits_a_failed_topup_the_gateway_later_reports_paid(): void
    {
        $this->actingAsAdmin();

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-LATE-APPROVAL',
            'gateway' => 'paystack',
            'amount' => 50.00,
            'charged_amount' => 51.50,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_FAILED,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['id' => 1, 'status' => 'success', 'reference' => 'TOP-LATE-APPROVAL', 'amount' => 5150, 'currency' => 'GHS'],
            ]),
        ]);

        $this->post(route('admin.topups.verify-payment', ['topup' => $topup->id]))->assertRedirect();
        $this->post(route('admin.topups.verify-payment', ['topup' => $topup->id]))->assertRedirect();

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->fresh()->status);
        $this->assertEquals(50.00, (float) $this->wallet->fresh()->balance); // credited once, not twice
    }

    public function test_reverify_of_a_still_failed_topup_says_failed_not_credited(): void
    {
        $this->actingAsAdmin();

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-STILL-FAILED',
            'gateway' => 'paystack',
            'amount' => 50.00,
            'charged_amount' => 51.50,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_FAILED,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['id' => 2, 'status' => 'failed', 'reference' => 'TOP-STILL-FAILED', 'amount' => 5150, 'currency' => 'GHS'],
            ]),
        ]);

        $this->post(route('admin.topups.verify-payment', ['topup' => $topup->id]))
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');

        $this->assertSame(BalanceTopup::STATUS_FAILED, $topup->fresh()->status);
        $this->assertEquals(0.00, (float) $this->wallet->fresh()->balance);
    }

    public function test_admin_reverify_does_not_double_credit_already_successful_topup(): void
    {
        $this->actingAsAdmin();

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-ALREADY-DONE',
            'gateway' => 'paystack',
            'amount' => 60.00,
            'charged_amount' => 61.80,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_SUCCESS,
        ]);

        $this->wallet->forceFill(['balance' => 60.00])->save();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 999890,
                    'status' => 'success',
                    'reference' => 'TOP-ALREADY-DONE',
                    'amount' => 6180,
                    'currency' => 'GHS',
                ],
            ]),
        ]);

        $response = $this->post(route('admin.topups.verify-payment', ['topup' => $topup->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Balance remains 60, not 120
        $this->assertEquals(60.00, (float) $this->wallet->fresh()->balance);
    }

    public function test_admin_reverify_keeps_pending_when_gateway_reports_pending(): void
    {
        $this->actingAsAdmin();

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-STILL-PENDING',
            'gateway' => 'moolre',
            'amount' => 50.00,
            'charged_amount' => 50.00,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        Http::fake([
            'api.moolre.com/*' => Http::response([
                'status' => 1,
                'data' => [
                    'txstatus' => 0, // 0 = pending
                    'amount' => 50.0,
                    'currency' => 'GHS',
                ],
            ]),
        ]);

        $response = $this->post(route('admin.topups.verify-payment', ['topup' => $topup->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Topup remains pending, wallet not credited
        $this->assertSame(BalanceTopup::STATUS_PENDING, $topup->fresh()->status);
        $this->assertEquals(0.00, (float) $this->wallet->fresh()->balance);
    }

    public function test_agent_can_verify_their_own_pending_topup(): void
    {
        $this->actingAs($this->agent, 'agent');

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-AGENT-VERIFY',
            'gateway' => 'paystack',
            'amount' => 80.00,
            'charged_amount' => 82.40,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 112233,
                    'status' => 'success',
                    'reference' => 'TOP-AGENT-VERIFY',
                    'amount' => 8240,
                    'currency' => 'GHS',
                ],
            ]),
        ]);

        $response = $this->post(route('agent.topup.verify', ['topup' => $topup->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(BalanceTopup::STATUS_SUCCESS, $topup->fresh()->status);
        $this->assertEquals(80.00, (float) $this->wallet->fresh()->balance);
    }

    public function test_agent_cannot_verify_another_agents_topup(): void
    {
        $otherAgent = Agent::factory()->create();
        $otherWallet = $otherAgent->walletOrCreate();

        $otherTopup = BalanceTopup::query()->create([
            'wallet_id' => $otherWallet->id,
            'reference' => 'TOP-OTHER-AGENT',
            'gateway' => 'paystack',
            'amount' => 50.00,
            'charged_amount' => 51.50,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        $this->actingAs($this->agent, 'agent');

        $response = $this->post(route('agent.topup.verify', ['topup' => $otherTopup->id]));

        $response->assertForbidden();
    }

    public function test_agent_history_mixes_uncredited_topups_with_the_ledger_newest_first(): void
    {
        $this->actingAs($this->agent, 'agent');

        $this->wallet->credit(20, 'topup', 'MTP_old_paid', 'Wallet top-up via Paystack');
        $this->travel(1)->minutes();
        $this->attempt('TOP-AGENT-FAILED', BalanceTopup::STATUS_FAILED);
        $this->travel(1)->minutes();
        $pending = $this->attempt('TOP-AGENT-PENDING', BalanceTopup::STATUS_PENDING);

        $this->get(route('agent.transactions'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('agent/transactions')
            ->missing('pendingTopups')
            ->has('transactions.data', 3)
            ->where('transactions.data.0.code', 'TOP-AGENT-PENDING')
            ->where('transactions.data.0.status', 'pending')
            ->where('transactions.data.0.topupId', $pending->id)
            ->where('transactions.data.0.balanceAfter', null)
            ->where('transactions.data.1.code', 'TOP-AGENT-FAILED')
            ->where('transactions.data.1.status', 'failed')
            ->where('transactions.data.2.code', 'MTP_old_paid')
            ->where('transactions.data.2.status', 'completed')
            ->missing('transactions.data.2.topupId')
        );
    }

    public function test_agent_history_status_and_type_filters_scope_the_topup_attempts(): void
    {
        $this->actingAs($this->agent, 'agent');

        $this->wallet->credit(20, 'topup', 'MTP_paid', 'Wallet top-up via Paystack');
        $this->attempt('TOP-PENDING-ONLY', BalanceTopup::STATUS_PENDING);
        $this->attempt('TOP-FAILED-ONLY', BalanceTopup::STATUS_FAILED);

        $this->get(route('agent.transactions', ['status' => 'pending']))->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.code', 'TOP-PENDING-ONLY')
        );

        $this->get(route('agent.transactions', ['status' => 'completed']))->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.code', 'MTP_paid')
        );

        // A purchase-only view never shows top-up attempts.
        $this->get(route('agent.transactions', ['type' => 'purchase']))->assertInertia(fn ($page) => $page
            ->has('transactions.data', 0)
        );
    }

    private function attempt(string $reference, string $status): BalanceTopup
    {
        return BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => $reference,
            'gateway' => 'paystack',
            'amount' => 95.00,
            'charged_amount' => 97.85,
            'currency' => 'GHS',
            'status' => $status,
        ]);
    }

    public function test_admin_can_export_topups(): void
    {
        $this->actingAsAdmin();

        $t1 = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-EXPORT-1',
            'gateway' => 'paystack',
            'amount' => 45.00,
            'charged_amount' => 46.35,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_SUCCESS,
        ]);

        $t2 = BalanceTopup::query()->create([
            'wallet_id' => $this->wallet->id,
            'reference' => 'TOP-EXPORT-2',
            'gateway' => 'moolre',
            'amount' => 70.00,
            'charged_amount' => 70.00,
            'currency' => 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        // Export all
        $response = $this->get(route('admin.topups.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        // Export selected
        $responseSelected = $this->get(route('admin.topups.export', ['ids' => (string) $t1->id]));
        $responseSelected->assertOk();
    }
}
