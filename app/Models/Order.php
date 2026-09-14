<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string $network
 * @property string $capacity_gb
 * @property string $beneficiary_phone
 * @property string $channel
 * @property string $source
 * @property string $payment_status
 * @property string $customer_price
 * @property string $seller_cost
 * @property string $agent_cost
 * @property string|null $base_cost
 * @property string $status
 * @property string|null $upstream_request_id
 * @property string|null $upstream_reference
 * @property string|null $upstream_status
 * @property string|null $upstream_cost
 * @property string|null $failure_reason
 * @property Carbon|null $last_polled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $refunded_at
 */
#[Fillable([
    'reference', 'idempotency_key', 'source', 'payment_status', 'gateway', 'gateway_reference',
    'network', 'capacity_gb', 'beneficiary_phone', 'customer_email',
    'channel', 'customer_price', 'seller_cost', 'agent_cost', 'base_cost',
    'status', 'upstream_request_id', 'upstream_reference', 'upstream_status', 'upstream_cost',
    'failure_reason', 'last_polled_at', 'completed_at', 'failed_at', 'refunded_at',
])]
class Order extends Model
{
    public const CHANNEL_PREPAID = 'prepaid';

    public const CHANNEL_ONLINE = 'online';

    // Where the order came from. Portal/API are seller-driven; storefront is a public customer.
    public const SOURCE_PORTAL = 'portal';

    public const SOURCE_API = 'api';

    public const SOURCE_STOREFRONT = 'storefront';

    // Payment lifecycle, orthogonal to `status`. Wallet-funded orders are PAID on creation;
    // storefront orders start AWAITING and only fulfill once the gateway confirms the payment.
    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_AWAITING = 'awaiting';

    public const PAYMENT_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected function casts(): array
    {
        return [
            'capacity_gb' => 'decimal:2',
            'customer_price' => 'decimal:2',
            'seller_cost' => 'decimal:2',
            'agent_cost' => 'decimal:2',
            'base_cost' => 'decimal:2',
            'upstream_cost' => 'decimal:2',
            'last_polled_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function seller(): MorphTo
    {
        return $this->morphTo();
    }
}
