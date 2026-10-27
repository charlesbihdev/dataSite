<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Models\BalanceTopup;
use App\Models\Earning;
use App\Models\Subagent;
use App\Models\WalletTransaction;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's Transactions page, two read-only tabs over two separate pools of money:
 *  - Earnings: margin earned per storefront sale (pending → credited on delivery), withdrawable.
 *  - Wallet: the deposit ledger — gateway top-ups in, Place-Order purchases out, refunds back.
 */
class TransactionsController extends Controller
{
    /**
     * Wallet filter keys → the underlying ledger types.
     *
     * @var array<string, list<string>>
     */
    private const WALLET_TYPE_FILTERS = [
        'topup' => ['topup'],
        'purchase' => ['order_purchase'],
        'refund' => ['order_refund', 'reversal'],
    ];

    public function index(Request $request): Response
    {
        $subagent = $request->user('subagent');

        $tab = $request->query('tab') === 'wallet' ? 'wallet' : 'earnings';
        $status = (string) $request->query('status', 'all');
        $type = (string) $request->query('type', 'all');
        $search = trim((string) $request->query('q', ''));
        $range = (string) $request->query('range', 'all');
        [$from, $to] = DateRange::resolve($request, $range);

        return Inertia::render('subagent/transactions', [
            'tab' => $tab,
            'stats' => [
                'available' => $subagent->earningsBalance(),
                'credited' => (float) $subagent->earnings()->where('status', Earning::STATUS_CREDITED)->sum('amount'),
                'pending' => (float) $subagent->earnings()->where('status', Earning::STATUS_PENDING)->sum('amount'),
                'walletBalance' => (float) ($subagent->wallet?->balance ?? 0),
            ],
            'transactions' => $tab === 'wallet'
                ? $this->walletLedger($subagent, $type, $search, $from, $to)
                : $this->earningsLedger($subagent, $status, $search, $from, $to),
            'pendingTopups' => $tab === 'wallet' ? $this->pendingTopups($subagent) : [],
            'filters' => [
                'status' => $status,
                'type' => $type,
                'q' => $search,
                'range' => $range,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }

    private function earningsLedger(Subagent $subagent, string $status, string $search, $from, $to)
    {
        return $subagent->earnings()->with('order')->latest('id')
            ->when(in_array($status, [Earning::STATUS_PENDING, Earning::STATUS_CREDITED, Earning::STATUS_REVERSED], true),
                fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($search !== '', fn ($q) => $q->whereHas('order', function ($o) use ($search) {
                $like = "%{$search}%";
                $o->where('reference', 'like', $like)->orWhere('beneficiary_phone', 'like', $like);
            }))
            ->paginate(30)->withQueryString()->through(fn (Earning $e): array => [
                'id' => $e->id,
                'type' => $this->earningLabel($e->type),
                'orderReference' => $e->order?->reference,
                'bundle' => $e->order ? strtoupper($e->order->network).' '.((float) $e->order->capacity_gb).'GB' : '—',
                'amount' => (float) $e->amount,
                'status' => $e->status,
                'date' => $e->created_at?->format('M j, Y H:i'),
            ]);
    }

    private function walletLedger(Subagent $subagent, string $type, string $search, $from, $to)
    {
        return $subagent->walletOrCreate()->transactions()->latest('id')
            ->when(isset(self::WALLET_TYPE_FILTERS[$type]), fn ($q) => $q->whereIn('type', self::WALLET_TYPE_FILTERS[$type]))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('reference', 'like', $like)->orWhere('description', 'like', $like);
            }))
            ->paginate(30)->withQueryString()->through(fn (WalletTransaction $t): array => [
                'id' => $t->id,
                'type' => $this->walletLabel($t->type),
                'reference' => $t->reference,
                'amount' => (float) $t->amount,
                'balanceAfter' => (float) $t->balance_after,
                'date' => $t->created_at?->format('M j, Y H:i'),
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingTopups(Subagent $subagent): array
    {
        return BalanceTopup::query()
            ->where('wallet_id', $subagent->walletOrCreate()->id)
            ->where('status', BalanceTopup::STATUS_PENDING)
            ->latest('id')
            ->get()
            ->map(fn (BalanceTopup $t): array => [
                'id' => $t->id,
                'reference' => $t->reference,
                'gateway' => ucfirst($t->gateway),
                'amount' => (float) $t->amount,
                'chargedAmount' => (float) $t->charged_amount,
                'date' => $t->created_at?->format('M j, Y H:i'),
            ])
            ->all();
    }

    private function earningLabel(string $type): string
    {
        return match ($type) {
            Earning::TYPE_SHOP_PROFIT => 'Sale profit',
            Earning::TYPE_COMMISSION => 'Commission',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    private function walletLabel(string $type): string
    {
        return match ($type) {
            'topup' => 'Wallet Top-up',
            'order_purchase' => 'Data Purchase',
            'order_refund' => 'Order Refund',
            'reversal' => 'Order Reversal',
            'adjustment' => 'Adjustment',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
