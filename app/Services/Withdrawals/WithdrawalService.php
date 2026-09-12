<?php

namespace App\Services\Withdrawals;

use App\Models\Withdrawal;

/**
 * Withdrawal state machine. Money isn't moved here — the earnings pool already reserves every
 * non-rejected withdrawal (see HasEarnings::earningsBalance), so a transition is just a status
 * change. Rejecting frees the reservation; paying records the out-of-band payout.
 */
class WithdrawalService
{
    /**
     * @return array<int, string> valid next statuses for a given current status
     */
    private const TRANSITIONS = [
        Withdrawal::STATUS_PENDING => [Withdrawal::STATUS_APPROVED, Withdrawal::STATUS_REJECTED],
        Withdrawal::STATUS_APPROVED => [Withdrawal::STATUS_PAID, Withdrawal::STATUS_REJECTED],
    ];

    /**
     * Apply a status transition if it's legal from the current status.
     */
    public function transition(Withdrawal $withdrawal, string $to, ?string $notes = null): bool
    {
        $allowed = self::TRANSITIONS[$withdrawal->status] ?? [];
        if (! in_array($to, $allowed, true)) {
            return false;
        }

        $withdrawal->status = $to;
        if ($notes !== null) {
            $withdrawal->admin_notes = $notes;
        }
        if (in_array($to, [Withdrawal::STATUS_PAID, Withdrawal::STATUS_REJECTED], true)) {
            $withdrawal->processed_at = now();
        }
        $withdrawal->save();

        return true;
    }
}
