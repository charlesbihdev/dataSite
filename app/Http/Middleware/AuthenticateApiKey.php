<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a developer API key from the request and attaches the record + its owner (the selling
 * agent/subagent) for downstream controllers. Lookup order mirrors Databundleshub: X-API-Key
 * header, then Authorization: Bearer, then ?api_key= query, then body. Only the hash is compared.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $this->rawKey($request);

        $apiKey = $raw === '' ? null : ApiKey::query()
            ->where('key_hash', ApiKey::hash($raw))
            ->where('is_active', true)
            ->with('owner')
            ->first();

        if ($apiKey === null || $apiKey->owner === null || $apiKey->owner->is_active === false) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or inactive API key.',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_seller', $apiKey->owner);

        return $next($request);
    }

    private function rawKey(Request $request): string
    {
        $header = trim((string) $request->header('X-API-Key', ''));
        if ($header !== '') {
            return $header;
        }

        $bearer = trim((string) $request->bearerToken());
        if ($bearer !== '') {
            return $bearer;
        }

        $query = trim((string) $request->query('api_key', ''));
        if ($query !== '') {
            return $query;
        }

        return trim((string) $request->input('api_key', ''));
    }
}
