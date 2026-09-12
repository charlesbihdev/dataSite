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

    public function topups(): Response
    {
        $rows = WalletTransaction::query()
            ->with('wallet.walletable')
            ->where('type', self::TYPE_TOPUP)
            ->latest('id')
            ->paginate(20)
            ->through(fn (WalletTransaction $t): array => $this->present($t));

        return Inertia::render('admin/topups', ['topups' => $rows]);
    }

    public function ledger(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $rows = WalletTransaction::query()
            ->with('wallet.walletable')
            ->when($search !== '', fn ($q) => $q->where('reference', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (WalletTransaction $t): array => $this->present($t));

        return Inertia::render('admin/ledger', [
            'transactions' => $rows,
            'filters' => ['q' => $search],
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
