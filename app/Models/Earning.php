<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $type
 * @property string $amount
 * @property string $status
 * @property Carbon|null $credited_at
 */
#[Fillable(['order_id', 'type', 'amount', 'status', 'credited_at'])]
class Earning extends Model
{
    public const TYPE_SHOP_PROFIT = 'shop_profit';

    public const TYPE_COMMISSION = 'commission';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_REVERSED = 'reversed';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'credited_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function earner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
