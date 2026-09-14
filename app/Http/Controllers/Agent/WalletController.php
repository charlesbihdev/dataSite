<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Ledger types stemming from the agent's own trading activity (never admin-driven).
     *
     * @var list<string>
     */
    private const USER_TYPES = ['order_purchase', 'order_refund', 'reversal', 'commission'];

    /**
     * Self-service gateway top-ups carry an "MTP" reference (WalletTopupService); admin funding
     * credits a bare "topup" with a null reference. That prefix is how we tell a USER-initiated
     * top-up (agent paid a gateway) from an ADMIN one (superadmin funded the wallet).
     */
    private const GATEWAY_TOPUP_REFERENCE_PREFIX = 'MTP';

    /**
     * Payment rail the money moved on: gateway top-ups vs balance movements. We don't record the
     * gateway on the ledger yet, so every top-up reads "Paystack"; everything else is "Wallet".
     *
     * @var array<string, list<string>>
     */
    private const PAYMENT_FILTERS = [
        'paystack' => ['topup'],
        'wallet' => ['order_purchase', 'adjustment', 'reversal', 'order_refund', 'commission'],
    ];

    public function index(Request $request): Response
    {
        $agent = $request->user();
        $wallet = $agent->walletOrCreate();

        $type = (string) $request->query('type', 'all');
        $source = (string) $request->query('source', 'all');
        $payment = (string) $request->query('payment', 'all');
        $search = trim((string) $request->query('q', ''));
        $range = (string) $request->query('range', 'all');
        [$from, $to] = DateRange::resolve($request, $range);

        $query = $wallet->transactions()->latest('id');
        if (isset(self::TYPE_FILTERS[$type])) {
            $query->whereIn('type', self::TYPE_FILTERS[$type]);
        }
        $this->applySourceFilter($query, $source);
        if (isset(self::PAYMENT_FILTERS[$payment])) {
            $query->whereIn('type', self::PAYMENT_FILTERS[$payment]);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('reference', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('type', 'like', $like);
            });
        }
        $query->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $transactions = $query->paginate(30)
            ->withQueryString()
            ->through(fn (WalletTransaction $t): array => $this->present($t));

        return Inertia::render('agent/transactions', [
            'balance' => (float) $wallet->balance,
            'transactions' => $transactions,
            'filters' => [
                'type' => $type,
                'source' => $source,
                'payment' => $payment,
                'q' => $search,
                'range' => $range,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WalletTransaction $t): array
    {
        $orderTypes = ['order_purchase', 'order_refund', 'reversal'];
        // A self-service gateway top-up (agent paid a gateway) is USER; admin funding/deductions are
        // ADMIN; everything else stemming from the agent's own trading is USER.
        $source = $this->isGatewayTopup($t)
            ? 'user'
            : (in_array($t->type, ['topup', 'adjustment'], true) ? 'admin' : 'user');

        return [
            'id' => $t->id,
            'type' => $this->sourceLabel($t->type),
            'direction' => (float) $t->amount >= 0 ? 'credit' : 'debit',
            'source' => $source,
            'amount' => (float) $t->amount,
            'orderReference' => in_array($t->type, $orderTypes, true) ? $t->reference : null,
            // No gateway is recorded on the ledger yet (top-up gateway is roadmap item #1), so
            // top-ups read "Paystack" and every balance movement reads "Wallet".
            'paymentSource' => $t->type === 'topup' ? 'Paystack' : 'Wallet',
            'status' => 'completed',
            'balanceBefore' => (float) $t->balance_before,
            'balanceAfter' => (float) $t->balance_after,
            'code' => $t->reference,
            'date' => $t->created_at?->format('M j, Y H:i'),
        ];
    }

    private function isGatewayTopup(WalletTransaction $t): bool
    {
        return $t->type === 'topup'
            && str_starts_with((string) $t->reference, self::GATEWAY_TOPUP_REFERENCE_PREFIX);
    }

    /**
     * Scope the ledger to ADMIN (superadmin funding/deductions) or USER (the agent's own trading,
     * including self-service gateway top-ups) so the filter matches the Source column exactly.
     *
     * @param  HasMany<WalletTransaction, Wallet>  $query
     */
    private function applySourceFilter($query, string $source): void
    {
        $like = self::GATEWAY_TOPUP_REFERENCE_PREFIX.'%';

        if ($source === 'admin') {
            $query->where(fn ($q) => $q->where('type', 'adjustment')
                ->orWhere(fn ($t) => $t->where('type', 'topup')
                    ->where(fn ($r) => $r->whereNull('reference')->orWhere('reference', 'not like', $like))));
        } elseif ($source === 'user') {
            $query->where(fn ($q) => $q->whereIn('type', self::USER_TYPES)
                ->orWhere(fn ($t) => $t->where('type', 'topup')->where('reference', 'like', $like)));
        }
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
