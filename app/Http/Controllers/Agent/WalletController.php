<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Transactions page: their balance plus the signed wallet_transactions ledger,
 * scoped to their own wallet and filterable by transaction type. Read-only — money only ever moves
 * through Wallet::credit/debit elsewhere (checkout, admin funding), which record these rows.
 */
class WalletController extends Controller
{
    /**
     * Filter keys → the underlying ledger types. Top-up = money in; debit = admin deductions;
     * purchase = bundle spend. Refund/commission/reversal remain visible under "all".
     *
     * @var array<string, list<string>>
     */
    private const TYPE_FILTERS = [
        'topup' => ['topup'],
        'debit' => ['adjustment'],
        'purchase' => ['order_purchase'],
    ];

    /**
     * Who initiated the movement — the same USER/ADMIN split rendered in the Source column, exposed
     * as a filter. ADMIN = wallet funding/deductions; USER = the agent's own trading activity.
     *
     * @var array<string, list<string>>
     */
    private const SOURCE_FILTERS = [
        'admin' => ['topup', 'adjustment'],
        'user' => ['order_purchase', 'order_refund', 'reversal', 'commission'],
    ];

    public function index(Request $request): Response
    {
        $agent = $request->user();
        $wallet = $agent->walletOrCreate();

        $type = (string) $request->query('type', 'all');
        $source = (string) $request->query('source', 'all');

        $query = $wallet->transactions()->latest('id');
        if (isset(self::TYPE_FILTERS[$type])) {
            $query->whereIn('type', self::TYPE_FILTERS[$type]);
        }
        if (isset(self::SOURCE_FILTERS[$source])) {
            $query->whereIn('type', self::SOURCE_FILTERS[$source]);
        }

        $transactions = $query->paginate(20)
            ->withQueryString()
            ->through(fn (WalletTransaction $t): array => $this->present($t));

        return Inertia::render('agent/transactions', [
            'balance' => (float) $wallet->balance,
            'transactions' => $transactions,
            'filters' => ['type' => $type, 'source' => $source],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WalletTransaction $t): array
    {
        $orderTypes = ['order_purchase', 'order_refund', 'reversal'];
        // Admin-driven types (funding / deductions) are ADMIN; anything stemming from the agent's
        // own trading is USER. (Self-service gateway top-ups will read USER once that lands.)
        $adminTypes = ['topup', 'adjustment'];

        return [
            'id' => $t->id,
            'type' => $this->sourceLabel($t->type),
            'direction' => (float) $t->amount >= 0 ? 'credit' : 'debit',
            'source' => in_array($t->type, $adminTypes, true) ? 'admin' : 'user',
            'amount' => (float) $t->amount,
            'orderReference' => in_array($t->type, $orderTypes, true) ? $t->reference : null,
            // No gateway is recorded on the ledger yet (top-up gateway is roadmap item #1).
            'paymentSource' => $t->type === 'topup' ? 'Top-up' : null,
            'status' => 'completed',
            'balanceBefore' => (float) $t->balance_before,
            'balanceAfter' => (float) $t->balance_after,
            'code' => $t->reference,
            'date' => $t->created_at?->format('M j, Y H:i'),
        ];
    }

    private function sourceLabel(string $type): string
    {
        return match ($type) {
            'topup' => 'Wallet Top-up',
            'order_purchase' => 'Data Purchase',
            'order_refund' => 'Order Refund',
            'reversal' => 'Order Reversal',
            'commission' => 'Commission',
            'adjustment' => 'Adjustment',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
