<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreWithdrawalRequest;
use App\Models\Earning;
use App\Models\Withdrawal;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Withdrawal page: request a payout of matured earnings and track past requests.
 * Withdrawals draw from the EARNINGS pool (credited commission), never the spendable deposit
 * wallet. earningsBalance() already reserves every non-rejected withdrawal, so a request is just a
 * pending row; the admin settles it out of band (WithdrawalService).
 */
class WithdrawalController extends Controller
{
    public function index(Request $request): Response
    {
        $earner = $request->user();
        $available = $earner->earningsBalance();

        return Inertia::render('agent/withdrawals', [
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
            'withdrawals' => $earner->withdrawals()->latest('id')->paginate(30)->through(fn (Withdrawal $w): array => [
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
        $earner = $request->user();
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
        abort_unless(
            $withdrawal->earner_type === $request->user()->getMorphClass() && $withdrawal->earner_id === $request->user()->getKey(),
            403,
        );

        if (! $service->transition($withdrawal, Withdrawal::STATUS_REJECTED, 'Cancelled by agent.')) {
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

        return to_route('agent.withdrawals');
    }
}
