<?php

namespace App\Services\Databundleshub;

use App\Models\DbhConfig;
use App\Models\UpstreamApiLog;
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
    public function placeOrder(string $reference, string $phone, int $capacityGb, string $network = '', ?int $orderId = null): UpstreamOrderResult
    {
        $url = $this->url('create_order');
        $payload = ['phoneNumber' => $phone, 'capacity' => $capacityGb, 'idempotencyKey' => $reference];

        return $this->execute('create', 'create_order', $network, $orderId, $url, $payload, ['reference' => $reference],
            fn (PendingRequest $http) => $http->post($url, $payload));
    }

    /**
     * Poll the status of a previously placed order by its upstream requestId.
     *
     * @throws UpstreamException on transport failure or missing config
     */
    public function orderStatus(string $requestId, string $network = '', ?int $orderId = null): UpstreamOrderResult
    {
        $url = $this->url('developer/purchase-status');
        $payload = ['request_id' => $requestId];

        return $this->execute('status', 'purchase-status', $network, $orderId, $url, $payload, ['request_id' => $requestId],
            fn (PendingRequest $http) => $http->get($url, $payload));
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
     * Run one endpoint call end to end: time it, translate failures via send()/toResult(), and write
     * an audit row (on success OR failure) to upstream_api_logs. The audit write never affects the
     * caller — any logging error is swallowed so a sale is never blocked by observability.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws UpstreamException
     */
    private function execute(string $operation, string $endpoint, string $network, ?int $orderId, string $url, array $payload, array $context, callable $call): UpstreamOrderResult
    {
        $start = microtime(true);
        $response = null;
        $result = null;
        $error = null;

        try {
            $response = $this->send($endpoint, $context, $call);
            $result = $this->toResult($response, $endpoint, $context);
            if (! $result->success) {
                $error = $result->errorMessage;
            }

            return $result;
        } catch (UpstreamException $e) {
            $error = $e->getMessage();

            throw $e;
        } finally {
            $this->record($operation, $network, $orderId, $url, $payload, $response, $result, $error, (int) round((microtime(true) - $start) * 1000));
        }
    }

    /**
     * Write one audit row. Never throws — a logging failure must not break a sale.
     *
     * @param  array<string, mixed>  $payload
     */
    private function record(string $operation, string $network, ?int $orderId, string $url, array $payload, ?Response $response, ?UpstreamOrderResult $result, ?string $error, int $durationMs): void
    {
        // Errors-only: skip clean successes (order placed / "still processing" poll) to avoid flooding.
        if ($result !== null && $result->success && $error === null) {
            return;
        }

        try {
            UpstreamApiLog::create([
                'order_id' => $orderId,
                'operation' => $operation,
                'network' => $network !== '' ? $network : null,
                'request_url' => $url,
                'request_payload' => $payload,
                'http_status' => $response?->status(),
                'response_body' => $response !== null ? mb_substr((string) $response->body(), 0, 5000) : null,
                'upstream_request_id' => $result?->requestId,
                'success' => $result?->success ?? false,
                'outcome' => $this->outcomeOf($operation, $result),
                'error_message' => $error !== null ? mb_substr($error, 0, 500) : null,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record upstream API log', ['error' => $e->getMessage()]);
        }
    }

    private function outcomeOf(string $operation, ?UpstreamOrderResult $result): string
    {
        return match (true) {
            $result === null => 'error',
            $result->isCompleted() => 'delivered',
            $result->isFailed() => 'failed',
            $operation === 'create' && $result->isAccepted() => 'accepted',
            default => 'processing',
        };
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

    // Normalize to the /api root. A misconfigured ".../api/developer" would 404 every status poll as
    // /developer/developer/... (create_order survived it only because DBH aliases it under both paths).
    public static function normalizeBaseUrl(string $baseUrl): string
    {
        $base = rtrim(trim($baseUrl), '/');

        return preg_replace('#/developer$#', '', $base) ?? $base;
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
