<?php

namespace App\Services\Databundleshub;

use App\Models\DbhConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Validate a base URL + API key against Databundleshub BEFORE we store them (admin settings).
     * First confirms the base URL resolves to the real API (public `get_pricing`), then confirms the
     * key is accepted (keyed `developer/data-packages`). Never throws — returns a plain result the
     * settings screen surfaces, so bad credentials are rejected at save time, not at first sale.
     *
     * @return array{ok: bool, message: string}
     */
    public function verifyConnection(string $baseUrl, string $apiKey): array
    {
        $root = self::normalizeBaseUrl($baseUrl);

        try {
            $pricing = Http::acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($root.'/get_pricing');

            if (! $pricing->successful() || ! is_array($pricing->json())) {
                return ['ok' => false, 'message' => "Base URL check failed (HTTP {$pricing->status()}) at {$root}/get_pricing — ".Str::limit((string) $pricing->body(), 120)];
            }

            $keyed = Http::withHeaders(['X-API-Key' => $apiKey])
                ->acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($root.'/developer/data-packages');
        } catch (ConnectionException $e) {
            return ['ok' => false, 'message' => "Could not reach {$root} — check the base URL. ({$e->getMessage()})"];
        }

        if (in_array($keyed->status(), [401, 403], true)) {
            return ['ok' => false, 'message' => "Databundleshub rejected the request (HTTP {$keyed->status()}): ".Str::limit((string) $keyed->body(), 120)];
        }

        if (! is_array($keyed->json()) || ($keyed->json()['success'] ?? false) !== true) {
            return ['ok' => false, 'message' => "API key check failed (HTTP {$keyed->status()}). ".Str::limit((string) $keyed->body(), 120)];
        }

        return ['ok' => true, 'message' => 'Connection verified.'];
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
        return self::normalizeBaseUrl($this->config()->base_url).'/'.ltrim($path, '/');
    }

    /**
     * Clean a configured base URL: trim surrounding whitespace and a single trailing slash so paths
     * don't double up ("//"). Nothing Databundleshub-specific — the base URL is stored exactly as
     * entered otherwise, so it stays fully configurable if the supplier's URL ever changes.
     */
    public static function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
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

        $result = UpstreamOrderResult::fromApiResponse($json);

        // A non-2xx or success:false envelope is not a transport failure (so we don't throw and let
        // the poller retry), but it IS an error the operator must see — log it so a held/rejected
        // order can be traced back to what Databundleshub actually said.
        if (! $response->successful() || ! $result->success) {
            $this->logFailure(
                $endpoint,
                $context + ['code' => $result->errorCode, 'error' => $result->errorMessage],
                'rejected',
                $response->status(),
            );
        }

        return $result;
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
