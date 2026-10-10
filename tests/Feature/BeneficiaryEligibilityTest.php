<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\PaymentGateway;
use App\Models\Subagent;
use App\Services\Beneficiaries\BeneficiaryEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BeneficiaryEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbhConfig::query()->create([
            'base_url' => 'https://dbh.test/api',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
    }

    public function test_service_returns_approved_for_valid_mtn_number(): void
    {
        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => true,
                'data' => [
                    [
                        'msisdn' => '0241234567',
                        'approved' => true,
                        'status' => 'approved',
                        'message' => null,
                    ],
                ],
            ]),
        ]);

        $service = app(BeneficiaryEligibilityService::class);
        $result = $service->check('0241234567');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['approved']);
        $this->assertSame('approved', $result['status']);
        $this->assertSame('0241234567', $result['msisdn']);
    }

    public function test_service_returns_not_approved_for_unapproved_mtn_number(): void
    {
        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => true,
                'data' => [
                    [
                        'msisdn' => '0558119182',
                        'approved' => false,
                        'status' => 'not_approved',
                        'message' => '0558119182 is not on your approved beneficiary list.',
                    ],
                ],
            ]),
        ]);

        $service = app(BeneficiaryEligibilityService::class);
        $result = $service->check('0558119182');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['approved']);
        $this->assertSame('not_approved', $result['status']);
        $this->assertStringContainsString('not on your approved', (string) $result['message']);
    }

    public function test_service_passes_telecel_to_dbh_and_returns_not_enforced(): void
    {
        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => false,
                'data' => [
                    [
                        'msisdn' => '0201234567',
                        'approved' => true,
                        'status' => 'not_enforced',
                        'message' => null,
                    ],
                ],
            ]),
        ]);

        $service = app(BeneficiaryEligibilityService::class);
        $result = $service->check('0201234567');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['approved']);
        $this->assertSame('not_enforced', $result['status']);
        $this->assertFalse($result['enforced']);
    }

    public function test_agent_storefront_eligibility_endpoint_returns_json(): void
    {
        $agent = Agent::factory()->create(['slug' => 'kofi-store']);

        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => true,
                'data' => [
                    [
                        'msisdn' => '0241234567',
                        'approved' => true,
                        'status' => 'approved',
                        'message' => null,
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson(route('agent.storefront.eligibility', ['agentSlug' => 'kofi-store']), [
            'phone' => '0241234567',
        ]);

        $response->assertOk()
            ->assertJsonPath('approved', true)
            ->assertJsonPath('status', 'approved');
    }

    public function test_subagent_storefront_eligibility_endpoint_returns_json(): void
    {
        $agent = Agent::factory()->create();
        $subagent = Subagent::create([
            'agent_id' => $agent->id,
            'name' => 'Ama',
            'phone' => '0247000002',
            'email' => 'ama@example.com',
            'username' => 'ama-bundles',
            'slug' => 'ama-bundles',
            'store_name' => 'Ama Bundles',
            'password' => 'secret',
            'is_active' => true,
            'store_active' => true,
        ]);

        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => true,
                'data' => [
                    [
                        'msisdn' => '0241234567',
                        'approved' => true,
                        'status' => 'approved',
                        'message' => null,
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson(route('subagent.storefront.eligibility', ['subagentSlug' => 'ama-bundles']), [
            'phone' => '0241234567',
        ]);

        $response->assertOk()
            ->assertJsonPath('approved', true)
            ->assertJsonPath('status', 'approved');
    }
}
