<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The withdrawal approval queue: pending → approved → paid, or rejected. Transitions run through
 * WithdrawalService so the legal state machine lives in one place.
 */
class WithdrawalsController extends Controller
{
    public function index(): Response
    {
        $withdrawals = Withdrawal::query()
            ->with('earner')
            ->latest('id')
            ->paginate(20)
            ->through(fn (Withdrawal $w): array => [
                'id' => $w->id,
                'earner' => ($w->earner?->name ?? 'Unknown').' ('.class_basename($w->earner_type).')',
                'amount' => (float) $w->amount,
                'status' => $w->status,
                'reference' => $w->reference,
                'notes' => $w->admin_notes,
                'requestedAt' => $w->created_at?->format('M j, Y H:i'),
                'processedAt' => $w->processed_at?->format('M j, Y H:i'),
            ]);

        return Inertia::render('admin/withdrawals', [
            'withdrawals' => $withdrawals,
            'summary' => [
                'pending' => Withdrawal::query()->where('status', Withdrawal::STATUS_PENDING)->count(),
                'approved' => Withdrawal::query()->where('status', Withdrawal::STATUS_APPROVED)->count(),
            ],
        ]);
    }

    public function update(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Withdrawal::STATUS_APPROVED,
                Withdrawal::STATUS_PAID,
                Withdrawal::STATUS_REJECTED,
            ])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $ok = $service->transition($withdrawal, $validated['status'], $validated['notes'] ?? null);

        Inertia::flash('toast', $ok
            ? ['type' => 'success', 'message' => "Withdrawal marked {$validated['status']}."]
            : ['type' => 'error', 'message' => "Can't move a {$withdrawal->status} withdrawal to {$validated['status']}."]);

        return back();
    }
}
