<?php

namespace App\Services\Storefront\StoreAssistant;

use App\Ai\Tools\LookupApiDocumentation;
use App\Support\GhanaMobileNetwork;

/**
 * The developer API reference the assistant hands back when a developer asks how to use our data API.
 * It mirrors the real surface in routes/api.php and App\Http\Controllers\Api\OrderController, and builds
 * the per-network bundle table straight from {@see GhanaMobileNetwork} so it never drifts from what the
 * API will actually accept.
 *
 * Firewall-safe: it documents how to CALL the API (endpoints, the X-API-Key header, fields, bundle sizes)
 * but never prints a brand name, a sign-up/"become an agent" path, or a concrete platform host — the host
 * is a placeholder the developer already knows from their own dashboard.
 *
 * There is intentionally NO keyword "detection" here — the model decides when to fetch this via the
 * {@see LookupApiDocumentation} tool.
 */
final class ApiDocs
{
    /** The reference block the lookup tool returns to the model. */
    public static function reference(): string
    {
        $networks = self::networks();
        $bundleTable = self::bundleTable();

        return <<<DOCS
        AUTH: every request needs your API key in the `X-API-Key` header (an `Authorization: Bearer <key>`
        header or `?api_key=` query also work). Generate the key in your own dashboard. Rate limit: 60
        requests per minute. All endpoints are under the `/api` base path. Requests and responses are JSON.

        NETWORKS & ACCEPTED VALUES:
        - `network` accepts: {$networks}. It is optional on an order — when omitted it is detected
          from the phone number's prefix.
        - `phoneNumber` accepts any Ghana mobile format: 0XXXXXXXXX, 233XXXXXXXXX, or the 9-digit form.
        - `capacity` is a whole number of GB and MUST be one of the bundle sizes allowed for that network
          (otherwise you get INVALID_CAPACITY):
        {$bundleTable}

        1) PLACE AN ORDER — POST /api/create_order   (alias: POST /api/developer/purchase)
           Body: phoneNumber (e.g. "0551234567"), capacity (e.g. 5), network (optional, see above).
           Idempotency (recommended): send an `Idempotency-Key` header (or body "idempotencyKey");
           repeating the same key returns the original order instead of charging again.
           201 Success: { "success": true, "data": { reference, idempotencyKey, network, capacity,
           phoneNumber, price, orderStatus, isCompleted, message, createdAt } }.

        2) CHECK ORDER STATUS — GET /api/order-status/{reference}
           Aliases: GET /api/developer/purchase-status/{reference}, GET /api/check_order_status?reference=...
           Returns the same data shape. orderStatus ∈ pending | processing | completed | failed | refunded;
           isCompleted is true once delivered. Poll until a terminal state (completed/failed).

        3) LIST PACKAGES & PRICES — GET /api/data-packages   (alias: GET /api/developer/data-packages)
           Optional ?network=mtn|telecel|at. Returns items: { capacity, mb, price, network, pricePerGB }.
           Prices are your own tier's rates, so prefer this endpoint over hard-coding the sizes above.

        ERRORS: { "success": false, "error": "...", "code": "..." }. Codes include INVALID_PHONE,
        INVALID_NETWORK, INVALID_CAPACITY, MISSING_FIELD, INVALID_PRICING, INSUFFICIENT_BALANCE (HTTP 409),
        ORDER_NOT_FOUND (HTTP 404).

        EXAMPLE:
          curl -X POST https://<your-api-host>/api/create_order \
            -H "X-API-Key: YOUR_KEY" -H "Content-Type: application/json" \
            -d '{"phoneNumber":"0551234567","capacity":5,"network":"mtn"}'
        DOCS;
    }

    /** "mtn (aliases: yello), telecel (aliases: vodafone), at (aliases: airteltigo)" — code + known aliases. */
    private static function networks(): string
    {
        $aliases = [
            GhanaMobileNetwork::MTN => ['yello'],
            GhanaMobileNetwork::TELECEL => ['vodafone'],
            GhanaMobileNetwork::AT => ['airteltigo', 'airtel', 'tigo'],
        ];

        return collect(GhanaMobileNetwork::order())
            ->map(fn (string $code): string => $code.' (aliases: '.implode(', ', $aliases[$code] ?? []).')')
            ->implode(', ');
    }

    /** One line per network with its allowed GB bundle sizes, pulled live from the network registry. */
    private static function bundleTable(): string
    {
        return collect(GhanaMobileNetwork::meta())
            ->map(fn (array $n): string => '          - '.$n['label'].' ('.$n['code'].'): '
                .implode(', ', $n['sizes']).' GB')
            ->implode("\n");
    }
}
