<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\BalanceTopup;
use App\Models\Subagent;
use App\Services\Payments\WalletTopupConfirmer;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopupsController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');
        $gateway = (string) $request->query('gateway', 'all');
        $seller = (string) $request->query('seller', 'all');
        $search = trim((string) $request->query('q', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = BalanceTopup::query()->with('wallet.walletable');

        if ($status !== 'all' && in_array($status, [BalanceTopup::STATUS_PENDING, BalanceTopup::STATUS_SUCCESS, BalanceTopup::STATUS_FAILED, BalanceTopup::STATUS_EXPIRED], true)) {
            $query->where('status', $status);
        }

        if ($gateway !== 'all' && in_array($gateway, ['paystack', 'moolre'], true)) {
            $query->where('gateway', $gateway);
        }

        $this->applySellerFilter($query, $seller);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('gateway_reference', 'like', "%{$search}%")
                    ->orWhereHas('wallet', function ($w) use ($search) {
                        $w->whereHasMorph('walletable', '*', function ($m) use ($search) {
                            $m->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    });
            });
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $stats = [
            'total_volume' => (float) BalanceTopup::where('status', BalanceTopup::STATUS_SUCCESS)->sum('amount'),
            'completed_count' => BalanceTopup::where('status', BalanceTopup::STATUS_SUCCESS)->count(),
            'pending_count' => BalanceTopup::where('status', BalanceTopup::STATUS_PENDING)->count(),
            'failed_count' => BalanceTopup::where('status', BalanceTopup::STATUS_FAILED)->count(),
            'total_count' => BalanceTopup::count(),
        ];

        $topups = $query->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BalanceTopup $t): array => $this->present($t));

        return Inertia::render('admin/topups', [
            'topups' => $topups,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'gateway' => $gateway,
                'seller' => $seller,
                'q' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function verifyPayment(BalanceTopup $topup, WalletTopupConfirmer $confirmer): RedirectResponse
    {
        $result = $confirmer->reverify($topup);

        $toastType = match ($result['status']) {
            'success' => 'success',
            'failed' => 'error',
            'error' => 'error',
            default => 'info',
        };

        Inertia::flash('toast', [
            'type' => $toastType,
            'message' => "{$topup->reference}: {$result['message']}",
        ]);

        return back();
    }

    public function export(Request $request): StreamedResponse
    {
        $status = (string) $request->query('status', 'all');
        $gateway = (string) $request->query('gateway', 'all');
        $seller = (string) $request->query('seller', 'all');
        $search = trim((string) $request->query('q', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = BalanceTopup::query()->with('wallet.walletable');

        $rawIds = $request->input('ids');
        if (! empty($rawIds)) {
            $ids = is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds);
            $ids = array_filter(array_map('intval', $ids), fn (int $id) => $id > 0);
            if (! empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        if ($status !== 'all' && in_array($status, [BalanceTopup::STATUS_PENDING, BalanceTopup::STATUS_SUCCESS, BalanceTopup::STATUS_FAILED, BalanceTopup::STATUS_EXPIRED], true)) {
            $query->where('status', $status);
        }

        if ($gateway !== 'all' && in_array($gateway, ['paystack', 'moolre'], true)) {
            $query->where('gateway', $gateway);
        }

        $this->applySellerFilter($query, $seller);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('gateway_reference', 'like', "%{$search}%")
                    ->orWhereHas('wallet', function ($w) use ($search) {
                        $w->whereHasMorph('walletable', '*', function ($m) use ($search) {
                            $m->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    });
            });
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $items = $query->latest('id')->get();

        $filename = "wallet-topups-{$status}-".now()->format('Y-m-d_His').'.csv';
        $headers = ['Reference', 'Gateway Ref', 'Type', 'Name', 'Phone', 'Amount (GHS)', 'Charged (GHS)', 'Gateway', 'Status', 'Date'];

        $rows = $items->map(function (BalanceTopup $t): array {
            $owner = $t->wallet?->walletable;

            return [
                $t->reference,
                $t->gateway_reference ?? '—',
                $this->sellerType($t),
                $owner?->name ?? 'Unknown',
                $owner?->phone ? CsvExport::text($owner->phone) : '—',
                number_format((float) $t->amount, 2),
                number_format((float) $t->charged_amount, 2),
                strtoupper($t->gateway),
                ucfirst($t->status),
                $t->created_at?->toDateTimeString() ?? '—',
            ];
        });

        return CsvExport::download($filename, $headers, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BalanceTopup $t): array
    {
        $owner = $t->wallet?->walletable;

        return [
            'id' => $t->id,
            'reference' => $t->reference,
            'gatewayReference' => $t->gateway_reference,
            'gateway' => $t->gateway,
            'gatewayLabel' => ucfirst($t->gateway),
            'amount' => (float) $t->amount,
            'chargedAmount' => (float) $t->charged_amount,
            'currency' => $t->currency,
            'status' => $t->status,
            'sellerType' => $this->sellerType($t),
            'agentName' => $owner?->name ?? 'Unknown',
            'agentEmail' => $owner?->email ?? '—',
            'agentPhone' => $owner?->phone ?? '—',
            'completedAt' => $t->completed_at?->format('M j, Y g:i A'),
            'createdAt' => $t->created_at?->format('M j, Y g:i A'),
        ];
    }

    /**
     * Narrow to top-ups funding an agent's or a subagent's wallet (the wallet's polymorphic owner).
     */
    private function applySellerFilter(Builder $query, string $seller): void
    {
        $type = match ($seller) {
            'agent' => Agent::class,
            'subagent' => Subagent::class,
            default => null,
        };

        if ($type !== null) {
            $query->whereHas('wallet', fn ($w) => $w->where('walletable_type', $type));
        }
    }

    /** "Agent" / "Subagent" — same labels as the orders pages' seller badge. */
    private function sellerType(BalanceTopup $t): string
    {
        return $t->wallet?->walletable_type !== null ? class_basename($t->wallet->walletable_type) : 'Unknown';
    }
}
