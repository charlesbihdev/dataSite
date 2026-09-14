<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\BalanceTopup;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Payments\MoolreClient;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Services\Payments\WalletTopupConfirmer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-to-server payment confirmation from Moolre — the reliable path when a payer never returns.
 * Moolre carries a shared `secret` in the body (not an HMAC), and its notification isn't itself
 * trusted for the amount: once the secret checks out we RE-VERIFY the transaction via the status
 * API before crediting. The actual credit is idempotent (shared WalletTopupConfirmer), so callback
 * + webhook can both fire and the wallet is still credited exactly once. Public, CSRF-exempt.
 */
class MoolreWebhookController extends Controller
{
    public function handle(Request $request, MoolreClient $moolre, WalletTopupConfirmer $confirmer, OrderPaymentConfirmer $orderConfirmer): JsonResponse
    {
        $gateway = PaymentGateway::forGateway(PaymentGateway::MOOLRE);
        if ($gateway === null || (string) $gateway->webhook_secret === '') {
            return response()->json(['ok' => false, 'message' => 'Webhook not configured'], 503);
        }

        $payload = $request->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        if (! $moolre->webhookSecretIsValid($gateway, (string) ($data['secret'] ?? ''))) {
            return response()->json(['ok' => false, 'message' => 'Invalid secret'], 401);
        }

        $reference = (string) ($data['externalref'] ?? $data['reference'] ?? '');
        if ($reference === '') {
            return response()->json(['ok' => false, 'message' => 'Missing reference'], 400);
        }

        $topup = BalanceTopup::query()
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('gateway_reference', $reference))
            ->first();

        if ($topup !== null) {
            $verified = $moolre->verify($gateway, $topup->reference);
            if (! $verified['ok']) {
                return response()->json(['ok' => false, 'message' => 'Verification unavailable'], 503);
            }

            $confirmer->confirm($topup, $verified['tx']);

            return response()->json(['ok' => true]);
        }

        // Not a wallet top-up — try a storefront order paid through the gateway (re-verified inside).
        $order = Order::query()
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('gateway_reference', $reference))
            ->first();

        if ($order === null) {
            return response()->json(['ok' => true, 'message' => 'Unknown reference']);
        }

        $orderConfirmer->confirm($order);

        return response()->json(['ok' => true]);
    }
}
