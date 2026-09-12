<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ApiKey;
use App\Models\BaseCost;
use App\Models\DbhConfig;
use App\Models\Order;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeveloperApiOrderTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    private string $rawKey;

    protected function setUp(): void
    {
        parent::setUp();

        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);

        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'mtn',
            'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 5.0, 'is_active' => true,
        ]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'cost_per_gb' => 3.0, 'is_active' => true]);

        $this->agent = Agent::create([
            'name' => 'Agent', 'phone' => '0551110000', 'password' => 'secret',
            'is_active' => true, 'pricing_tier_id' => $tier->id,
        ]);
        $this->agent->walletOrCreate()->credit(100, 'topup');

        [, $this->rawKey] = ApiKey::generate($this->agent, 'Production');
    }

    private function fakeUpstream(array $data): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response(['success' => true, 'data' => $data])]);
    }

    public function test_api_places_an_order_and_debits_the_seller_at_tier_price(): void
    {
        $this->fakeUpstream(['requestId' => 5, 'orderStatus' => 'completed', 'price' => 15.0]);

        $response = $this->withHeaders(['X-API-Key' => $this->rawKey])
            ->postJson('/api/create_order', ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price', 25)            // 5 GB * 5.00/GB
            ->assertJsonPath('data.orderStatus', 'completed');

        $order = Order::first();
        $this->assertSame('api', $order->source);
        $this->assertSame(75.0, (float) $this->agent->walletOrCreate()->balance); // 100 - 25
    }

    public function test_missing_key_is_rejected(): void
    {
        $this->postJson('/api/create_order', ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_bad_phone_is_rejected_before_any_charge(): void
    {
        $this->withHeaders(['X-API-Key' => $this->rawKey])
            ->postJson('/api/create_order', ['phoneNumber' => '12345', 'network' => 'mtn', 'capacity' => 5])
            ->assertStatus(400)
            ->assertJsonPath('code', 'INVALID_PHONE');

        $this->assertSame(0, Order::count());
        $this->assertSame(100.0, (float) $this->agent->walletOrCreate()->balance);
    }

    public function test_idempotency_returns_the_same_order_without_double_charging(): void
    {
        $this->fakeUpstream(['requestId' => 6, 'orderStatus' => 'completed', 'price' => 15.0]);
        $payload = ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5];
        $headers = ['X-API-Key' => $this->rawKey, 'Idempotency-Key' => 'abc-123'];

        $first = $this->withHeaders($headers)->postJson('/api/create_order', $payload)->assertStatus(201);
        $second = $this->withHeaders($headers)->postJson('/api/create_order', $payload)->assertStatus(200);

        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
        $this->assertTrue($second->json('data.duplicate'));
        $this->assertSame(1, Order::count());
        $this->assertSame(75.0, (float) $this->agent->walletOrCreate()->balance);
    }

    public function test_insufficient_balance_is_reported(): void
    {
        $this->agent->walletOrCreate()->debit(90, 'spend'); // leaves 10, order costs 25
        $this->fakeUpstream(['requestId' => 7, 'orderStatus' => 'completed']);

        $this->withHeaders(['X-API-Key' => $this->rawKey])
            ->postJson('/api/create_order', ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSUFFICIENT_BALANCE');
    }

    public function test_status_endpoint_returns_the_order(): void
    {
        $this->fakeUpstream(['requestId' => 8, 'orderStatus' => 'completed', 'price' => 15.0]);
        $reference = $this->withHeaders(['X-API-Key' => $this->rawKey])
            ->postJson('/api/create_order', ['phoneNumber' => '0559999999', 'network' => 'mtn', 'capacity' => 5])
            ->json('data.reference');

        $this->withHeaders(['X-API-Key' => $this->rawKey])
            ->getJson("/api/order-status/{$reference}")
            ->assertStatus(200)
            ->assertJsonPath('data.orderStatus', 'completed')
            ->assertJsonPath('data.isCompleted', true);
    }
}
