<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $gateway
 * @property string $reference
 * @property string|null $gateway_reference
 * @property string $amount
 * @property string $charged_amount
 * @property string $currency
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'wallet_id', 'gateway', 'reference', 'gateway_reference',
    'amount', 'charged_amount', 'currency', 'status', 'metadata', 'completed_at',
])]
class BalanceTopup extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'charged_amount' => 'decimal:2',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
