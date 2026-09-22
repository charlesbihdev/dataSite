<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's Transactions page — their EARNINGS/commission ledger (not a deposit wallet: subagents
 * never top up or spend a balance). Each row is the margin earned on a storefront sale, moving
 * pending → credited on delivery (or reversed on a failed order). Read-only.
 */
class TransactionsController extends Controller
{
    public function index(Request $request): Response
    {
        $subagent = $request->user('subagent');

        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        $range = (string) $request->query('range', 'all');
        [$from, $to] = DateRange::resolve($request, $range);

        $query = $subagent->earnings()->with('order')->latest('id')
            ->when(in_array($status, [Earning::STATUS_PENDING, Earning::STATUS_CREDITED, Earning::STATUS_REVERSED], true),
                fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($search !== '', fn ($q) => $q->whereHas('order', function ($o) use ($search) {
                $like = "%{$search}%";
                $o->where('reference', 'like', $like)->orWhere('beneficiary_phone', 'like', $like);
            }));

        $transactions = $query->paginate(30)->withQueryString()->through(fn (Earning $e): array => [
            'id' => $e->id,
            'type' => $this->typeLabel($e->type),
            'orderReference' => $e->order?->reference,
            'bundle' => $e->order ? strtoupper($e->order->network).' '.((float) $e->order->capacity_gb).'GB' : '—',
            'amount' => (float) $e->amount,
            'status' => $e->status,
            'date' => $e->created_at?->format('M j, Y H:i'),
        ]);

        return Inertia::render('subagent/transactions', [
            'stats' => [
                'available' => $subagent->earningsBalance(),
                'credited' => (float) $subagent->earnings()->where('status', Earning::STATUS_CREDITED)->sum('amount'),
                'pending' => (float) $subagent->earnings()->where('status', Earning::STATUS_PENDING)->sum('amount'),
            ],
            'transactions' => $transactions,
            'filters' => [
                'status' => $status,
                'q' => $search,
                'range' => $range,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            Earning::TYPE_SHOP_PROFIT => 'Sale profit',
            Earning::TYPE_COMMISSION => 'Commission',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
