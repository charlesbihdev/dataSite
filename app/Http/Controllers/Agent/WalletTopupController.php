<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\WalletTopupRequest;
use App\Models\BalanceTopup;
use App\Models\PaymentGateway;
use App\Services\Payments\MoolreClient;
use App\Services\Payments\PaystackClient;
use App\Services\Payments\TopupException;
use App\Services\Payments\WalletTopupConfirmer;
use App\Services\Payments\WalletTopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Agent self-service wallet funding. `store` opens a gateway checkout (real money in); `callback`
 * is where the gateway returns the payer — we verify server-side and credit the wallet. The webhook
 * (Webhooks\PaystackWebhookController) is the reliable path for payers who never make it back here.
 */
class WalletTopupController extends Controller
{
    public function store(WalletTopupRequest $request, WalletTopupService $service): HttpResponse
    {
        $agent = $request->user('agent');
        $wallet = $agent->walletOrCreate();

        try {
            $result = $service->initiate(
                $wallet,
                (string) $agent->email,
                (float) $request->validated('amount'),
                route('agent.topup.callback'),
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
        $agent = $request->user('agent');
        $reference = trim((string) $request->query('reference', ''));

        $topup = BalanceTopup::query()
            ->where('wallet_id', $agent->walletOrCreate()->id)
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

    private function redirectWithToast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return redirect()->route('agent.transactions');
    }
}
