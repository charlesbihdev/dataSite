<?php

namespace App\Models;

use App\Exceptions\InsufficientBalanceException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $balance
 * @property string $currency
 */
#[Fillable(['currency'])]
class Wallet extends Model
{
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Add funds. Records a ledger row and updates the cached balance atomically.
     */
    public function credit(float $amount, string $type, ?string $reference = null, ?string $description = null): WalletTransaction
    {
        return $this->record(abs($amount), $type, $reference, $description);
    }

    /**
     * Remove funds. Throws InsufficientBalanceException if it would go negative.
     */
    public function debit(float $amount, string $type, ?string $reference = null, ?string $description = null): WalletTransaction
    {
        return $this->record(-abs($amount), $type, $reference, $description);
    }

    /**
     * The single guarded path for every balance movement: lock the row, compute
     * before/after, write the ledger, update the cached balance — all in one
     * transaction. Never update `balance` outside this method.
     */
    protected function record(float $signedAmount, string $type, ?string $reference, ?string $description): WalletTransaction
    {
        return DB::transaction(function () use ($signedAmount, $type, $reference, $description): WalletTransaction {
            /** @var self $wallet */
            $wallet = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $before = (float) $wallet->balance;
            $after = round($before + $signedAmount, 2);

            if ($after < 0) {
                throw new InsufficientBalanceException(
                    "Wallet {$wallet->id} balance {$before} cannot cover a debit of ".abs($signedAmount).'.'
                );
            }

            $wallet->balance = $after;
            $wallet->save();

            $transaction = $wallet->transactions()->create([
                'type' => $type,
                'amount' => $signedAmount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference,
                'description' => $description,
            ]);

            $this->balance = $after; // keep the in-memory instance in sync

            return $transaction;
        });
    }
}
