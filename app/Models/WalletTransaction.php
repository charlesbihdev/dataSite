<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $type
 * @property string $amount
 * @property string $balance_before
 * @property string $balance_after
 * @property string|null $reference
 * @property string|null $description
 */
#[Fillable(['type', 'amount', 'balance_before', 'balance_after', 'reference', 'description'])]
class WalletTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
