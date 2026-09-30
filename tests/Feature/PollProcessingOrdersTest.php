<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PollProcessingOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
    }

    private function processingOrder(string $ref, ?string $requestId): Order
    {
        $agent = Agent::factory()->create();

        return $agent->orders()->create([
            'reference' => $ref, 'idempotency_key' => $ref, 'source' => Order::SOURCE_PORTAL,
            'payment_status' => Order::PAYMENT_PAID, 'network' => 'mtn', 'capacity_gb' => 5,
            'beneficiary_phone' => '0241234567', 'channel' => Order::CHANNEL_PREPAID,
            'customer_price' => 30, 'seller_cost' => 25, 'agent_cost' => 20, 'base_cost' => 15,
            'status' => Order::STATUS_PROCESSING, 'upstream_request_id' => $requestId,
        ]);
    }

    public function test_sweep_settles_a_processing_order_that_upstream_reports_delivered(): void
    {
        Http::fake(['dbh.test/api/developer/purchase-status*' => Http::response([
            'success' => true, 'data' => ['requestId' => 9, 'orderStatus' => 'delivered', 'price' => 15.0],
        ])]);
        $order = $this->processingOrder('DS-SW1', '9');

        $this->artisan('orders:poll-processing')->assertSuccessful();

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame('15.00', $order->fresh()->upstream_cost);
    }

    public function test_sweep_leaves_a_still_processing_order_alone(): void
    {
        Http::fake(['dbh.test/api/developer/purchase-status*' => Http::response([
            'success' => true, 'data' => ['requestId' => 9, 'orderStatus' => 'processing'],
        ])]);
        $order = $this->processingOrder('DS-SW2', '9');

        $this->artisan('orders:poll-processing')->assertSuccessful();

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_sweep_skips_orders_without_an_upstream_reference(): void
    {
        Http::fake();
        $this->processingOrder('DS-SW3', null);

        $this->artisan('orders:poll-processing')->assertSuccessful();

        Http::assertNothingSent();
    }
}
