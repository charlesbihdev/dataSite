<?php

namespace App\Services\Wallet;

use App\Models\BalanceTopup;
use App\Models\Wallet;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One newest-first history for a wallet: every completed ledger movement (wallet_transactions) plus
 * every top-up attempt that hasn't credited (pending, failed or expired balance_topups), ordered purely by
 * time like the admin Top-ups page. A successful top-up appears once — as its ledger credit — so
 * nothing doubles. Rows are plain objects tagged `kind` = ledger | topup for the caller to present.
 */
final class WalletHistory
{
    /** Status filter value for completed ledger movements. */
    public const STATUS_COMPLETED = 'completed';

    /**
     * @param  string  $status  all | completed | pending | failed | expired
     * @param  bool  $includeAttempts  false when the caller's other filters rule top-ups out
     * @param  (Closure(Builder): void)|null  $scopeLedger  caller-specific ledger filters (type, source…)
     */
    public function paginate(
        Wallet $wallet,
        string $status,
        bool $includeAttempts,
        string $search,
        mixed $from,
        mixed $to,
        ?Closure $scopeLedger = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        $parts = [];

        if (in_array($status, ['all', self::STATUS_COMPLETED], true)) {
            $parts[] = $this->ledger($wallet, $search, $from, $to, $scopeLedger);
        }

        $attemptStatuses = $status === 'all'
            ? [BalanceTopup::STATUS_PENDING, BalanceTopup::STATUS_FAILED, BalanceTopup::STATUS_EXPIRED]
            : array_values(array_intersect([$status], [BalanceTopup::STATUS_PENDING, BalanceTopup::STATUS_FAILED, BalanceTopup::STATUS_EXPIRED]));

        if ($includeAttempts && $attemptStatuses !== []) {
            $parts[] = $this->attempts($wallet, $attemptStatuses, $search, $from, $to);
        }

        // Nothing selectable (e.g. "pending" + a purchase-only filter): an empty ledger query keeps
        // the paginator shape intact.
        $union = array_shift($parts) ?? $this->ledger($wallet, $search, $from, $to, fn (Builder $q) => $q->whereRaw('1 = 0'));
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return DB::query()->fromSub($union, 'history')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function ledger(Wallet $wallet, string $search, mixed $from, mixed $to, ?Closure $scopeLedger): Builder
    {
        $query = DB::table('wallet_transactions')
            ->where('wallet_id', $wallet->id)
            ->select([
                'id', DB::raw("'ledger' as kind"), 'type', 'amount', 'reference', 'description',
                'balance_before', 'balance_after', DB::raw('NULL as gateway'),
                DB::raw("'".self::STATUS_COMPLETED."' as status"), 'created_at',
            ])
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $like = "%{$search}%";
                $q->where('reference', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('type', 'like', $like);
            }));

        if ($scopeLedger !== null) {
            $scopeLedger($query);
        }

        return $this->withinRange($query, $from, $to);
    }

    /**
     * @param  list<string>  $statuses
     */
    private function attempts(Wallet $wallet, array $statuses, string $search, mixed $from, mixed $to): Builder
    {
        $query = DB::table('balance_topups')
            ->where('wallet_id', $wallet->id)
            ->whereIn('status', $statuses)
            ->select([
                'id', DB::raw("'topup' as kind"), DB::raw("'topup' as type"), 'amount', 'reference',
                DB::raw('NULL as description'), DB::raw('NULL as balance_before'), DB::raw('NULL as balance_after'),
                'gateway', 'status', 'created_at',
            ])
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $like = "%{$search}%";
                $q->where('reference', 'like', $like)->orWhere('gateway_reference', 'like', $like);
            }));

        return $this->withinRange($query, $from, $to);
    }

    private function withinRange(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to));
    }
}
