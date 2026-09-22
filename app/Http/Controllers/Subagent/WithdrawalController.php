<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subagent\StoreWithdrawalRequest;
use App\Models\Earning;
use App\Models\Withdrawal;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's Withdrawal page — the exact same earnings-pool payout flow as the agent's
 * (App\Http\Controllers\Agent\WithdrawalController), scoped to the subagent guard. Withdrawals draw
 * from matured EARNINGS (credited commission), never the spendable deposit wallet; earningsBalance()
 * already reserves every non-rejected withdrawal, so a request is just a pending row the admin settles.
 */
class WithdrawalController extends Controller
{
    public function index(Request $request): Response
    {
        $earner = $request->user('subagent');
        $available = $earner->earningsBalance();

        return Inertia::render('subagent/withdrawals', [
            'stats' => [
                'totalEarnings' => (float) $earner->earnings()->where('status', Earning::STATUS_CREDITED)->sum('amount'),
                'available' => $available,
                'pending' => (float) $earner->withdrawals()->whereIn('status', [Withdrawal::STATUS_PENDING, Withdrawal::STATUS_APPROVED])->sum('amount'),
                'withdrawn' => (float) $earner->withdrawals()->where('status', Withdrawal::STATUS_PAID)->sum('amount'),
            ],
            'methods' => collect(config('withdrawals.methods'))
                ->map(fn (array $m, string $key): array => [
                    'key' => $key,
                    'label' => $m['label'],
                    'min' => (float) $m['min'],
                    'enabled' => $available >= (float) $m['min'],
                ])
                ->values(),
            'withdrawals' => $earner->withdrawals()->latest('id')->simplePaginate(30)->through(fn (Withdrawal $w): array => [
                'id' => $w->id,
                'method' => $this->methodLabel($w->method),
                'amount' => (float) $w->amount,
                'status' => $w->status,
                'date' => $w->created_at?->format('j M Y'),
                'canCancel' => $w->status === Withdrawal::STATUS_PENDING,
            ]),
        ]);
    }

    public function store(StoreWithdrawalRequest $request): RedirectResponse
    {
        $earner = $request->user('subagent');
        $method = $request->string('method')->value();
        $amount = round((float) $request->input('amount'), 2);
        $min = (float) config("withdrawals.methods.{$method}.min");

        if ($amount < $min) {
            return $this->toast('error', "The minimum {$this->methodLabel($method)} withdrawal is GHS ".number_format($min, 2).'.');
        }
        if ($amount > $earner->earningsBalance()) {
            return $this->toast('error', 'Amount exceeds your available balance.');
        }

        $earner->withdrawals()->create([
            'amount' => $amount,
            'method' => $method,
            'destination' => $request->string('destination')->value(),
            'status' => Withdrawal::STATUS_PENDING,
            'reference' => 'WD-'.strtoupper(Str::random(8)),
        ]);

        return $this->toast('success', 'Withdrawal request submitted for review.');
    }

    public function cancel(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $earner = $request->user('subagent');

        abort_unless(
            $withdrawal->earner_type === $earner->getMorphClass() && $withdrawal->earner_id === $earner->getKey(),
            403,
        );

        if (! $service->transition($withdrawal, Withdrawal::STATUS_REJECTED, 'Cancelled by subagent.')) {
            return $this->toast('error', 'This withdrawal can no longer be cancelled.');
        }

        return $this->toast('success', 'Withdrawal request cancelled.');
    }

    private function methodLabel(?string $method): string
    {
        return $method !== null ? (string) config("withdrawals.methods.{$method}.label", ucfirst($method)) : '—';
    }

    private function toast(string $level, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $level, 'message' => $message]);

        return to_route('subagent.withdrawals');
    }
}
