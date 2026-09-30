<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs inbound developer-API requests (resellers buying data / polling status) to api_request_logs.
 * Runs outside api.key so bad-key 401s are captured too; never breaks the request.
 */
class LogApiRequest
{
    private const MAX_BODY = 4000;

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        try {
            $this->record($request, $response, (int) round((microtime(true) - $startedAt) * 1000));
        } catch (\Throwable $e) {
            Log::warning('Failed to record inbound API request log', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    private function record(Request $request, Response $response, int $durationMs): void
    {
        /** @var Model|null $seller */
        $seller = $request->attributes->get('api_seller');
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $status = $response->getStatusCode();
        $json = $this->decode($response);
        $success = $status < 400 && ($json['success'] ?? true) !== false;

        ApiRequestLog::create([
            'seller_type' => $seller?->getMorphClass(),
            'seller_id' => $seller?->getKey(),
            'api_key_id' => $apiKey?->getKey(),
            'method' => $request->getMethod(),
            'endpoint' => ltrim($request->path(), '/'),
            'network' => $this->network($request, $json),
            'request_payload' => $this->safeBody($request),
            'http_status' => $status,
            'response_body' => $this->truncate($response->getContent()),
            'success' => $success,
            'error_code' => is_string($json['code'] ?? null) ? $json['code'] : null,
            'error_message' => is_string($json['error'] ?? null) ? $json['error'] : null,
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    private function safeBody(Request $request): ?array
    {
        $body = $request->except(['api_key', 'apiKey', 'key']);

        return $body === [] ? null : $body;
    }

    private function network(Request $request, array $json): ?string
    {
        $fromRequest = strtolower(trim((string) $request->input('network', '')));
        if ($fromRequest !== '') {
            return $fromRequest;
        }

        $fromResponse = $json['data']['network'] ?? null;

        return is_string($fromResponse) ? strtolower($fromResponse) : null;
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function truncate(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        return mb_strlen($body) > self::MAX_BODY ? mb_substr($body, 0, self::MAX_BODY).'…' : $body;
    }
}
