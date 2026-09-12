<?php

namespace App\Models\Concerns;

use App\Models\Earning;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Earnings pool for an account type (agent, subagent): the withdrawable profit,
 * kept SEPARATE from the spendable deposit wallet (see HasWallet).
 */
trait HasEarnings
{
    /** @return MorphMany<Earning, $this> */
    public function earnings(): MorphMany
    {
        return $this->morphMany(Earning::class, 'earner');
    }

    /** @return MorphMany<Withdrawal, $this> */
    public function withdrawals(): MorphMany
    {
        return $this->morphMany(Withdrawal::class, 'earner');
    }

    /**
     * Withdrawable balance = credited earnings minus every non-rejected withdrawal
     * (pending/approved/paid are all reserved so the amount can't be requested twice).
     */
    public function earningsBalance(): float
    {
        $credited = (float) $this->earnings()
            ->where('status', Earning::STATUS_CREDITED)
            ->sum('amount');

        $reserved = (float) $this->withdrawals()
            ->whereIn('status', [
                Withdrawal::STATUS_PENDING,
                Withdrawal::STATUS_APPROVED,
                Withdrawal::STATUS_PAID,
            ])
            ->sum('amount');

        return round($credited - $reserved, 2);
    }
}
