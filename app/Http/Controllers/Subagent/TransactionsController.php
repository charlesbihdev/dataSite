<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\Subagent;
use App\Services\Wallet\WalletHistory;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's Transactions page, two read-only tabs over two separate pools of money:
 *  - Earnings: margin earned per storefront sale (pending → credited on delivery), withdrawable.
 *  - Wallet: the deposit history — top-ups in (incl. pending/failed attempts with Verify),
 *    Place-Order purchases out, refunds back.
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

    public function index(Request $request, WalletHistory $history): Response
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
                ? $this->walletHistory($history, $subagent, $type, $status, $search, $from, $to)
                : $this->earningsLedger($subagent, $status, $search, $from, $to),
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

    /**
     * Newest-first wallet history: ledger movements mixed with gateway top-ups still pending or
     * failed (those carry a topupId so the page can offer Verify).
     */
    private function walletHistory(WalletHistory $history, Subagent $subagent, string $type, string $status, string $search, $from, $to)
    {
        return $history->paginate(
            $subagent->walletOrCreate(), $status, in_array($type, ['all', 'topup'], true), $search, $from, $to,
            fn (Builder $q) => $q->when(isset(self::WALLET_TYPE_FILTERS[$type]), fn ($q) => $q->whereIn('type', self::WALLET_TYPE_FILTERS[$type])),
        )->through(fn (object $row): array => [
            'id' => $row->id,
            'topupId' => $row->kind === 'topup' ? $row->id : null,
            'type' => $this->walletLabel($row->type),
            'reference' => $row->reference,
            'amount' => (float) $row->amount,
            'status' => $row->status,
            'balanceAfter' => $row->balance_after !== null ? (float) $row->balance_after : null,
            'date' => Carbon::parse($row->created_at)->format('M j, Y H:i'),
        ]);
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
