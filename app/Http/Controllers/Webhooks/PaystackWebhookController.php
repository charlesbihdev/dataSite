<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\BalanceTopup;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Payments\OrderPaymentConfirmer;
use App\Services\Payments\PaystackClient;
use App\Services\Payments\WalletTopupConfirmer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-to-server payment confirmation from Paystack — the reliable path when a payer closes the
 * tab before the redirect callback runs. Every request is HMAC-verified before we trust it, and the
 * actual crediting is idempotent (shared WalletTopupConfirmer), so callback + webhook can both fire
 * and the wallet is still credited exactly once. Public, CSRF-exempt (see bootstrap/app.php).
 */
class PaystackWebhookController extends Controller
{
    public function handle(Request $request, PaystackClient $paystack, WalletTopupConfirmer $confirmer, OrderPaymentConfirmer $orderConfirmer): JsonResponse
    {
        $gateway = PaymentGateway::forGateway(PaymentGateway::PAYSTACK);
        if ($gateway === null) {
            return response()->json(['ok' => false], 401);
        }

        $rawPayload = $request->getContent();
        $signature = (string) $request->header('X-Paystack-Signature', '');
        if (! $paystack->signatureIsValid($gateway, $rawPayload, $signature)) {
            return response()->json(['ok' => false, 'message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        $event = is_array($payload) ? (string) ($payload['event'] ?? '') : '';
        $tx = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = (string) ($tx['reference'] ?? '');

        if ($reference === '' || ! in_array($event, ['charge.success', 'charge.failed'], true)) {
            return response()->json(['ok' => true, 'message' => 'Event ignored']);
        }

        $topup = BalanceTopup::query()
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('gateway_reference', $reference))
            ->first();

        if ($topup !== null) {
            $confirmer->confirm($topup, $paystack->normalize($tx));

            return response()->json(['ok' => true]);
        }

        // Not a wallet top-up — try a storefront order paid through the gateway.
        $order = Order::query()
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('gateway_reference', $reference))
            ->first();

        if ($order !== null) {
            $orderConfirmer->confirm($order);
        }

        return response()->json(['ok' => true]);
    }
}
