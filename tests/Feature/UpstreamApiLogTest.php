<?php

namespace Tests\Feature;

use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Order;
use App\Models\UpstreamApiLog;
use App\Services\Orders\NewOrderData;
use App\Services\Orders\OrderDispatchService;
use App\Services\Orders\OrderPoller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UpstreamApiLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
    }

    private function agent(): Agent
    {
        return Agent::create(['name' => 'Agent', 'phone' => '0551110000', 'password' => 'secret', 'is_active' => true]);
    }

    private function placeOrder(): Order
    {
        $agent = $this->agent();
        $agent->walletOrCreate()->credit(100, 'topup');

        return app(OrderDispatchService::class)->dispatch(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
        ));
    }

    public function test_create_order_records_a_successful_api_log(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 7, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);

        $order = $this->placeOrder();

        $log = UpstreamApiLog::where('operation', 'create')->firstOrFail();
        $this->assertSame($order->id, $log->order_id);
        $this->assertSame('mtn', $log->network);
        $this->assertTrue($log->success);
        $this->assertSame(200, $log->http_status);
        $this->assertSame('delivered', $log->outcome);
        $this->assertSame('0559999999', $log->request_payload['phoneNumber']);
        $this->assertNotNull($log->duration_ms);
    }

    public function test_poll_records_a_status_api_log(): void
    {
        Queue::fake();
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 9, 'orderStatus' => 'processing'],
        ])]);
        $order = $this->placeOrder();

        Http::fake(['dbh.test/api/developer/purchase-status*' => Http::response([
            'success' => true, 'data' => ['requestId' => 9, 'orderStatus' => 'delivered', 'price' => 15.0],
        ])]);
        (new PollUpstreamOrderStatus($order->id))->handle(app(OrderPoller::class));

        $log = UpstreamApiLog::where('operation', 'status')->firstOrFail();
        $this->assertSame($order->id, $log->order_id);
        $this->assertTrue($log->success);
        $this->assertSame('delivered', $log->outcome);
    }

    public function test_bot_blocked_call_records_a_failed_api_log_with_the_response_body(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response('<html>Access denied</html>', 200)]);

        $this->placeOrder();

        $log = UpstreamApiLog::where('operation', 'create')->firstOrFail();
        $this->assertFalse($log->success);
        $this->assertSame('error', $log->outcome);
        $this->assertStringContainsString('Access denied', (string) $log->response_body);
    }
}
