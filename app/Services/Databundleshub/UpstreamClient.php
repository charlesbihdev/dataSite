<?php

namespace App\Services\Databundleshub;

use App\Models\DbhConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the Databundleshub supplier API (fulfillment pipe only — see ARCHITECTURE §2).
 * Connection comes from the active `dbh_config` row; no prices, no cache. One method per endpoint,
 * responses mapped to UpstreamOrderResult. The API key is never logged.
 */
class UpstreamClient
{
    private const TIMEOUT_SECONDS = 30;

    private const CONNECT_TIMEOUT_SECONDS = 10;

    private ?DbhConfig $config = null;

    /**
     * Place an order upstream. Our order reference doubles as the idempotency key, so a
     * retry with the same reference returns the existing result rather than double-charging.
     *
     * @throws UpstreamException on transport failure or missing config
     */
    public function placeOrder(string $reference, string $phone, int $capacityGb): UpstreamOrderResult
    {
        $response = $this->send(
            'create_order',
            ['reference' => $reference],
            fn (PendingRequest $http) => $http->post($this->url('create_order'), [
                'phoneNumber' => $phone,
                'capacity' => $capacityGb,
                'idempotencyKey' => $reference,
            ]),
        );

        return $this->toResult($response, 'create_order', ['reference' => $reference]);
    }

    /**
     * Poll the status of a previously placed order by its upstream requestId.
     *
     * @throws UpstreamException on transport failure or missing config
     */
    public function orderStatus(string $requestId): UpstreamOrderResult
    {
        $response = $this->send(
            'purchase-status',
            ['request_id' => $requestId],
            fn (PendingRequest $http) => $http->get($this->url('developer/purchase-status'), [
                'request_id' => $requestId,
            ]),
        );

        return $this->toResult($response, 'purchase-status', ['request_id' => $requestId]);
    }

    /**
     * Run an HTTP call against the authenticated client, translating transport failures
     * (connect timeout, DNS, refused) into UpstreamException.
     *
     * @param  array<string, mixed>  $context
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws UpstreamException
     */
    private function send(string $endpoint, array $context, callable $call): Response
    {
        try {
            return $call($this->request());
        } catch (ConnectionException $e) {
            $this->logFailure($endpoint, $context, 'connection_error', 0);

            throw new UpstreamException("Databundleshub {$endpoint} was unreachable: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Build a request pre-authenticated with the active connection's key.
     *
     * @throws UpstreamException when no active connection is configured
     */
    private function request(): PendingRequest
    {
        return Http::withHeaders(['X-API-Key' => $this->config()->api_key])
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS);
    }

    private function url(string $path): string
    {
        $base = rtrim($this->config()->base_url, '/');

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * Active connection row, resolved once per client instance.
     *
     * @throws UpstreamException when no active connection is configured
     */
    private function config(): DbhConfig
    {
        return $this->config ??= DbhConfig::active()
            ?? throw new UpstreamException('No active Databundleshub connection is configured.');
    }

    /**
     * @param  array<string, mixed>  $context
     *
     * @throws UpstreamException
     */
    private function toResult(Response $response, string $endpoint, array $context): UpstreamOrderResult
    {
        if ($response->serverError()) {
            $this->logFailure($endpoint, $context, 'server_error', $response->status());

            throw new UpstreamException("Databundleshub {$endpoint} returned {$response->status()}.");
        }

        $json = $response->json();
        if (! is_array($json)) {
            $this->logFailure($endpoint, $context, 'non_json', $response->status());

            throw new UpstreamException("Databundleshub {$endpoint} returned a non-JSON response.");
        }

        return UpstreamOrderResult::fromApiResponse($json);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $endpoint, array $context, string $reason, int $status): void
    {
        Log::warning('Databundleshub upstream call failed', [
            'endpoint' => $endpoint,
            'reason' => $reason,
            'http_status' => $status,
        ] + $context);
    }
}
