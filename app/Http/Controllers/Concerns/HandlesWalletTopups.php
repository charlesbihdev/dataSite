<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\Agent\WalletTopupRequest;
use App\Models\BalanceTopup;
use App\Models\PaymentGateway;
use App\Services\Payments\MoolreClient;
use App\Services\Payments\PayerEmail;
use App\Services\Payments\PaystackClient;
use App\Services\Payments\TopupException;
use App\Services\Payments\WalletTopupConfirmer;
use App\Services\Payments\WalletTopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Self-service wallet funding shared by the agent and subagent portals. `store` opens a gateway
 * checkout (real money in); `callback` is where the gateway returns the payer — we verify
 * server-side and credit the wallet. The webhooks are the reliable path for payers who never make it
 * back here. The owner is always the guard's user, so a top-up can only ever touch their own wallet.
 */
trait HandlesWalletTopups
{
    /** Named route the gateway returns the payer to. */
    abstract protected function callbackRoute(): string;

    /** Where the payer lands after the callback, to see their updated balance. */
    abstract protected function afterCallbackUrl(): string;

    public function store(WalletTopupRequest $request, WalletTopupService $service): HttpResponse
    {
        $owner = $request->user();

        try {
            $result = $service->initiate(
                $owner->walletOrCreate(),
                PayerEmail::resolve($owner->email, $owner->phone),
                (float) $request->validated('amount'),
                route($this->callbackRoute()),
            );
        } catch (TopupException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        // Hand the browser off to the gateway's hosted checkout page.
        return Inertia::location($result['authorization_url']);
    }

    public function callback(Request $request, PaystackClient $paystack, MoolreClient $moolre, WalletTopupConfirmer $confirmer): RedirectResponse
    {
        $reference = trim((string) $request->query('reference', ''));

        $topup = BalanceTopup::query()
            ->where('wallet_id', $request->user()->walletOrCreate()->id)
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('gateway_reference', $reference))
            ->first();

        if ($reference === '' || $topup === null) {
            return $this->redirectWithToast('error', 'We could not find that payment. If you were charged, it will reflect shortly.');
        }

        $gateway = $confirmer->gatewayFor($topup);
        if ($gateway === null) {
            return $this->redirectWithToast('error', 'Payment verification is unavailable right now.');
        }

        $verified = $topup->gateway === PaymentGateway::MOOLRE
            ? $moolre->verify($gateway, $topup->reference)
            : $paystack->verify($gateway, $reference);
        $outcome = $verified['ok']
            ? $confirmer->confirm($topup, $verified['tx'])
            : WalletTopupConfirmer::INVALID;

        return match ($outcome) {
            WalletTopupConfirmer::SUCCESS, WalletTopupConfirmer::IGNORED => $this->redirectWithToast(
                'success',
                'Wallet topped up with GHS '.number_format((float) $topup->amount, 2).'.',
            ),
            WalletTopupConfirmer::FAILED => $this->redirectWithToast('error', 'Your payment did not go through. Please try again.'),
            default => $this->redirectWithToast('info', 'Your payment is still being confirmed. Your balance will update once it clears.'),
        };
    }

    public function verify(Request $request, BalanceTopup $topup, WalletTopupConfirmer $confirmer): RedirectResponse
    {
        abort_unless((int) $topup->wallet_id === (int) $request->user()->walletOrCreate()->id, 403);

        $result = $confirmer->reverify($topup);

        $toastType = match ($result['status']) {
            'success' => 'success',
            'failed' => 'error',
            'error' => 'error',
            default => 'info',
        };

        Inertia::flash('toast', ['type' => $toastType, 'message' => $result['message']]);

        return back();
    }

    private function redirectWithToast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return redirect()->to($this->afterCallbackUrl());
    }
}
