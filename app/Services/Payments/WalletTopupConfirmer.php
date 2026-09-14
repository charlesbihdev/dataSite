<?php

namespace App\Services\Payments;

use App\Models\BalanceTopup;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single, idempotent path that turns a confirmed gateway transaction into a wallet credit.
 * Shared by both the redirect callback and the webhook — and by both gateways — so a top-up is
 * credited EXACTLY once no matter how many times we're told it succeeded. It consumes a
 * gateway-agnostic NORMALIZED transaction (see the shape below) that each client produces, so this
 * class never needs to know Paystack from Moolre. The credited amount always comes from the stored
 * top-up record and is re-checked against the gateway's reported amount — never trusted from the
 * browser (payment invariant #11: the credit and the status flip commit together).
 *
 * Normalized transaction shape:
 *   [
 *     'status'       => 'success' | 'failed' | 'pending',
 *     'amount_minor' => int,        // minor units (kobo / pesewas)
 *     'currency'     => string,     // '' when the gateway doesn't report it
 *     'reference'    => string,     // gateway or our reference, for matching
 *     'topup_id'     => ?int,       // from metadata when available, else null
 *   ]
 */
class WalletTopupConfirmer
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const IGNORED = 'ignored';   // already terminal — nothing to do (idempotent no-op)

    public const INVALID = 'invalid';   // transaction doesn't match this top-up — do NOT credit

    /**
     * @param  array{status?: string, amount_minor?: int, currency?: string, reference?: string, topup_id?: int|null}  $tx
     */
    public function confirm(BalanceTopup $topup, array $tx): string
    {
        $status = strtolower((string) ($tx['status'] ?? ''));

        if ($status !== 'success') {
            return $this->markFailedIfPending($topup, $status);
        }

        if (! $this->transactionMatches($topup, $tx)) {
            Log::warning('Wallet top-up transaction mismatch', ['topup_id' => $topup->id, 'reference' => $topup->reference]);

            return self::INVALID;
        }

        return DB::transaction(function () use ($topup, $tx): string {
            /** @var BalanceTopup $fresh */
            $fresh = BalanceTopup::query()->whereKey($topup->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->status !== BalanceTopup::STATUS_PENDING) {
                return self::IGNORED;
            }

            $wallet = $fresh->wallet;
            $wallet->credit(
                (float) $fresh->amount,
                'topup',
                $fresh->reference,
                'Wallet top-up via '.ucfirst($fresh->gateway),
            );

            $fresh->update([
                'status' => BalanceTopup::STATUS_SUCCESS,
                'completed_at' => now(),
                'gateway_reference' => (string) ($tx['reference'] ?? $fresh->gateway_reference),
                'metadata' => $tx,
            ]);

            return self::SUCCESS;
        });
    }

    private function markFailedIfPending(BalanceTopup $topup, string $status): string
    {
        if ($status === 'failed' && $topup->status === BalanceTopup::STATUS_PENDING) {
            $topup->update(['status' => BalanceTopup::STATUS_FAILED]);

            return self::FAILED;
        }

        return self::IGNORED;
    }

    /**
     * Amount, currency and reference must line up with our record before we credit; topup_id is
     * checked only when the gateway echoes it back (Paystack metadata; Moolre has none, so the
     * unique reference lookup is the binding there).
     *
     * @param  array{amount_minor?: int, currency?: string, reference?: string, topup_id?: int|null}  $tx
     */
    private function transactionMatches(BalanceTopup $topup, array $tx): bool
    {
        $expectedMinor = (int) round((float) $topup->charged_amount * 100);
        if (abs((int) ($tx['amount_minor'] ?? 0) - $expectedMinor) > 1) {
            return false;
        }

        $currency = strtoupper((string) ($tx['currency'] ?? ''));
        if ($currency !== '' && $currency !== strtoupper((string) $topup->currency)) {
            return false;
        }

        $reference = (string) ($tx['reference'] ?? '');
        if ($reference !== '' && $reference !== $topup->reference && $reference !== (string) $topup->gateway_reference) {
            return false;
        }

        $topupId = $tx['topup_id'] ?? null;

        return $topupId === null || (int) $topupId === (int) $topup->id;
    }

    /**
     * Look up the gateway config for a top-up (used by callers verifying against the gateway API).
     */
    public function gatewayFor(BalanceTopup $topup): ?PaymentGateway
    {
        return PaymentGateway::forGateway($topup->gateway);
    }
}
