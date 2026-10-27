<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Wallet\WalletHistory;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Transactions page: their balance plus one newest-first history (WalletHistory) — the
 * signed wallet_transactions ledger mixed with any gateway top-ups still pending or failed, which
 * carry a Verify action. Filterable by type, source, payment rail and status. Read-only — money only
 * ever moves through Wallet::credit/debit elsewhere (checkout, admin funding, top-up confirmation).
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

    public function index(Request $request, WalletHistory $history): Response
    {
        $agent = $request->user();
        $wallet = $agent->walletOrCreate();

        $type = (string) $request->query('type', 'all');
        $source = (string) $request->query('source', 'all');
        $payment = (string) $request->query('payment', 'all');
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        $range = (string) $request->query('range', 'all');
        [$from, $to] = DateRange::resolve($request, $range);

        // Gateway top-up attempts are user-initiated Paystack/Moolre money-in, so they only belong
        // in the list when the type/source/payment filters would include such a top-up.
        $includeAttempts = in_array($type, ['all', 'topup'], true)
            && in_array($source, ['all', 'user'], true)
            && in_array($payment, ['all', 'paystack'], true);

        $transactions = $history->paginate(
            $wallet, $status, $includeAttempts, $search, $from, $to,
            function (Builder $query) use ($type, $source, $payment): void {
                if (isset(self::TYPE_FILTERS[$type])) {
                    $query->whereIn('type', self::TYPE_FILTERS[$type]);
                }
                $this->applySourceFilter($query, $source);
                if (isset(self::PAYMENT_FILTERS[$payment])) {
                    $query->whereIn('type', self::PAYMENT_FILTERS[$payment]);
                }
            },
        )->through(fn (object $row): array => $row->kind === 'topup' ? $this->presentAttempt($row) : $this->present($row));

        return Inertia::render('agent/transactions', [
            'balance' => (float) $wallet->balance,
            'transactions' => $transactions,
            'filters' => [
                'type' => $type,
                'source' => $source,
                'payment' => $payment,
                'status' => $status,
                'q' => $search,
                'range' => $range,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }

    /**
     * A ledger movement (money actually moved).
     *
     * @return array<string, mixed>
     */
    private function present(object $t): array
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
            'date' => Carbon::parse($t->created_at)->format('M j, Y H:i'),
        ];
    }

    /**
     * A gateway top-up that hasn't credited (pending or failed) — no balance has moved yet, and the
     * row carries its topupId so the page can offer Verify.
     *
     * @return array<string, mixed>
     */
    private function presentAttempt(object $t): array
    {
        return [
            'id' => $t->id,
            'topupId' => $t->id,
            'type' => 'Wallet Top-up',
            'direction' => 'credit',
            'source' => 'user',
            'amount' => (float) $t->amount,
            'orderReference' => null,
            'paymentSource' => ucfirst((string) $t->gateway),
            'status' => $t->status,
            'balanceBefore' => null,
            'balanceAfter' => null,
            'code' => $t->reference,
            'date' => Carbon::parse($t->created_at)->format('M j, Y H:i'),
        ];
    }

    private function isGatewayTopup(object $t): bool
    {
        return $t->type === 'topup'
            && str_starts_with((string) $t->reference, self::GATEWAY_TOPUP_REFERENCE_PREFIX);
    }

    /**
     * Scope the ledger to ADMIN (superadmin funding/deductions) or USER (the agent's own trading,
     * including self-service gateway top-ups) so the filter matches the Source column exactly.
     */
    private function applySourceFilter(Builder $query, string $source): void
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
