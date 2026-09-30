<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per call to Databundleshub (create_order on dispatch, purchase-status on each poll).
 * The missing observability layer: what we sent, what came back, how long it took. Append-only —
 * the API key lives in a header and is never in the payload/response, so nothing secret is stored.
 *
 * @property int $id
 * @property int|null $order_id
 * @property string $operation
 * @property string|null $network
 * @property string $request_url
 * @property array<string, mixed>|null $request_payload
 * @property int|null $http_status
 * @property string|null $response_body
 * @property string|null $upstream_request_id
 * @property bool $success
 * @property string|null $outcome
 * @property string|null $error_message
 * @property int|null $duration_ms
 */
#[Fillable(['order_id', 'operation', 'network', 'request_url', 'request_payload', 'http_status', 'response_body', 'upstream_request_id', 'success', 'outcome', 'error_message', 'duration_ms', 'created_at'])]
class UpstreamApiLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Prune audit rows older than 6 months (run by the scheduler's model:prune). */
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
