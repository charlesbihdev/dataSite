<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per inbound developer-API request (a reseller calling our API to buy data / poll status).
 * Append-only; the API key rides in a header and is redacted from any stored body.
 *
 * @property int $id
 * @property string|null $seller_type
 * @property int|null $seller_id
 * @property int|null $api_key_id
 * @property int|null $order_id
 * @property string $method
 * @property string $endpoint
 * @property string|null $network
 * @property array<string, mixed>|null $request_payload
 * @property int|null $http_status
 * @property string|null $response_body
 * @property bool $success
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int|null $duration_ms
 * @property string|null $ip
 */
#[Fillable(['seller_type', 'seller_id', 'api_key_id', 'order_id', 'method', 'endpoint', 'network', 'request_payload', 'http_status', 'response_body', 'success', 'error_code', 'error_message', 'duration_ms', 'ip', 'created_at'])]
class ApiRequestLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(6));
    }

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'success' => 'boolean',
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function seller(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
