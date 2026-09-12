<?php

namespace App\Models\Concerns;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Gives an account type (agent, subagent) a single polymorphic wallet.
 */
trait HasWallet
{
    /** @return MorphOne<Wallet, $this> */
    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }

    /**
     * The owner's wallet, created on first access if it doesn't exist yet.
     */
    public function walletOrCreate(): Wallet
    {
        return $this->wallet()->firstOrCreate([]);
    }
}
