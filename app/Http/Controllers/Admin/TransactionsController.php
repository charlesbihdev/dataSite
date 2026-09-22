<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only views over the wallet_transactions ledger. Top-ups is the deposit log (credits of
 * type "topup", agents + subagents combined); Ledger is the full signed audit trail.
 */
class TransactionsController extends Controller
{
    public const TYPE_TOPUP = 'topup';

    public function ledger(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = WalletTransaction::query()
            ->with('wallet.walletable');

        if ($search !== '') {
            $query->where(
                fn ($q) => $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas(
                        'wallet',
                        fn ($w) => $w->whereHasMorph(
                            'walletable',
                            '*',
                            fn ($m) => $m->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                        )
                    )
            );
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $statsRow = (clone $query)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('SUM(CASE WHEN amount > 0 THEN 1 ELSE 0 END) as credit_transactions')
            ->selectRaw('SUM(CASE WHEN amount < 0 THEN 1 ELSE 0 END) as debit_transactions')
            ->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_credits')
            ->selectRaw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_debits')
            ->selectRaw('SUM(amount) as net_change')
            ->first();

        $stats = [
            'total_transactions' => (int) ($statsRow->total_transactions ?? 0),
            'credit_transactions' => (int) ($statsRow->credit_transactions ?? 0),
            'debit_transactions' => (int) ($statsRow->debit_transactions ?? 0),
            'total_credits' => (float) ($statsRow->total_credits ?? 0),
            'total_debits' => (float) ($statsRow->total_debits ?? 0),
            'net_change' => (float) ($statsRow->net_change ?? 0),
        ];

        $rows = $query->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (WalletTransaction $t): array => $this->present($t));

        return Inertia::render('admin/ledger', [
            'transactions' => $rows,
            'filters' => [
                'q' => $search,
                'type' => $type,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WalletTransaction $t): array
    {
        $owner = $t->wallet?->walletable;

        return [
            'id' => $t->id,
            'owner' => $owner
                ? ($owner->name.' ('.class_basename($t->wallet->walletable_type).')')
                : 'Unknown',
            'type' => $t->type,
            'amount' => (float) $t->amount,
            'balanceAfter' => (float) $t->balance_after,
            'reference' => $t->reference,
            'description' => $t->description,
            'createdAt' => $t->created_at?->format('M j, Y H:i'),
        ];
    }
}
