<?php

namespace Tests\Feature;

use App\Models\DbhConfig;
use App\Services\Databundleshub\UpstreamClient;
use App\Services\Databundleshub\UpstreamException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpstreamClientTest extends TestCase
{
    use RefreshDatabase;

    private function activeConfig(): DbhConfig
    {
        return DbhConfig::create([
            'base_url' => 'https://dbh.test/api',
            'api_key' => 'secret-key',
            'is_active' => true,
        ]);
    }

    public function test_place_order_sends_key_and_maps_completed_response(): void
    {
        $this->activeConfig();
        Http::fake([
            'dbh.test/api/create_order' => Http::response([
                'success' => true,
                'data' => [
                    'requestId' => 42,
                    'transactionReference' => 'TRX-9',
                    'orderStatus' => 'completed',
                    'price' => 5.5,
                    'remainingBalance' => 100.0,
                ],
            ]),
        ]);

        $result = app(UpstreamClient::class)->placeOrder('OUR-REF-1', '0551234567', 5);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('42', $result->requestId);
        $this->assertSame(5.5, $result->price);

        Http::assertSent(function ($request) {
            return $request['idempotencyKey'] === 'OUR-REF-1'
                && $request['capacity'] === 5
                && $request->hasHeader('X-API-Key', 'secret-key');
        });
    }

    public function test_business_rejection_returns_failed_result_not_exception(): void
    {
        $this->activeConfig();
        Http::fake([
            'dbh.test/api/create_order' => Http::response([
                'success' => false,
                'error' => 'Insufficient balance at purchase time.',
                'code' => 'INSUFFICIENT_BALANCE',
                'data' => ['orderStatus' => 'rejected'],
            ], 409),
        ]);

        $result = app(UpstreamClient::class)->placeOrder('OUR-REF-2', '0551234567', 5);

        $this->assertTrue($result->isFailed());
        $this->assertSame('INSUFFICIENT_BALANCE', $result->errorCode);
    }

    public function test_server_error_throws_upstream_exception(): void
    {
        $this->activeConfig();
        Http::fake(['dbh.test/api/*' => Http::response('boom', 500)]);

        $this->expectException(UpstreamException::class);

        app(UpstreamClient::class)->orderStatus('42');
    }

    public function test_missing_config_throws_upstream_exception(): void
    {
        Http::fake();

        $this->expectException(UpstreamException::class);

        app(UpstreamClient::class)->orderStatus('42');
    }
}
