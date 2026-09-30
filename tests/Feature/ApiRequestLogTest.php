<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use App\Models\BaseCost;
use App\Models\DbhConfig;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiRequestLogTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    private string $rawKey;

    protected function setUp(): void
    {
        parent::setUp();

        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        Http::fake(['dbh.test/api/create_order' => Http::response(['success' => true, 'data' => ['requestId' => 5, 'orderStatus' => 'completed', 'price' => 15.0]])]);

        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create(['pricing_tier_id' => $tier->id, 'network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 5.0, 'is_active' => true]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'cost_per_gb' => 3.0, 'is_active' => true]);

        $this->agent = Agent::create(['name' => 'Kwame', 'phone' => '0551110000', 'password' => 'secret', 'is_active' => true, 'pricing_tier_id' => $tier->id]);
        $this->agent->walletOrCreate()->credit(100, 'topup');

        [, $this->rawKey] = ApiKey::generate($this->agent, 'Production');
    }

    public function test_a_purchase_call_is_logged_with_caller_and_outcome(): void
    {
        $this->withHeaders(['X-API-Key' => $this->rawKey])
            ->postJson('/api/create_order', ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5])
            ->assertStatus(201);

        $log = ApiRequestLog::sole();
        $this->assertTrue($log->success);
        $this->assertSame(201, $log->http_status);
        $this->assertSame('mtn', $log->network);
        $this->assertSame('api/create_order', $log->endpoint);
        $this->assertSame('POST', $log->method);
        $this->assertSame($this->agent->id, $log->seller_id);
        $this->assertSame($this->agent->getMorphClass(), $log->seller_type);
        $this->assertSame('0559999999', $log->request_payload['phoneNumber']);
    }

    public function test_a_bad_key_attempt_is_logged_without_a_seller(): void
    {
        $this->postJson('/api/create_order', ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5])
            ->assertStatus(401);

        $log = ApiRequestLog::sole();
        $this->assertFalse($log->success);
        $this->assertSame(401, $log->http_status);
        $this->assertNull($log->seller_id);
        $this->assertSame('UNAUTHORIZED', $log->error_code);
    }

    public function test_a_business_rejection_is_logged_as_failed_with_the_error(): void
    {
        $this->agent->walletOrCreate()->debit(95, 'spend'); // leaves 5, order costs 25

        $this->withHeaders(['X-API-Key' => $this->rawKey])
            ->postJson('/api/create_order', ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5])
            ->assertStatus(409);

        $log = ApiRequestLog::sole();
        $this->assertFalse($log->success);
        $this->assertSame('INSUFFICIENT_BALANCE', $log->error_code);
    }

    public function test_an_api_key_sent_in_the_body_is_redacted_from_the_log(): void
    {
        $this->postJson('/api/create_order', ['api_key' => $this->rawKey, 'phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5])
            ->assertStatus(201);

        $log = ApiRequestLog::sole();
        $this->assertArrayNotHasKey('api_key', $log->request_payload ?? []);
    }
}
