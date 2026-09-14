<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for Moolre's hosted-checkout ("embed link") and transaction-status APIs, plus
 * webhook auth. Unlike Paystack, Moolre amounts cross the wire in MAJOR units (GHS), auth is a pair
 * of headers (X-API-USER / X-API-PUBKEY), and webhooks carry a shared `secret` in the body rather
 * than an HMAC signature — so the reliable confirmation is to re-verify via the status API.
 */
class MoolreClient
{
    private const LINK_URL = 'https://api.moolre.com/embed/link';

    private const STATUS_URL = 'https://api.moolre.com/open/transact/status';

    /**
     * Create a hosted-checkout link for a payment and return where to send the payer.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{ok: bool, authorization_url: ?string, gateway_reference: ?string, message: string}
     */
    public function initialize(PaymentGateway $gateway, string $email, float $amount, string $reference, string $redirectUrl, array $metadata = []): array
    {
        $response = $this->request($gateway)->post(self::LINK_URL, [
            'accountnumber' => (string) $gateway->moolre_account_number,
            'type' => 1,
            'currency' => $gateway->currency ?: 'GHS',
            'reusable' => 0,
            'amount' => number_format($amount, 2, '.', ''),
            'email' => $email,
            'externalref' => $reference,
            'redirect' => $redirectUrl,
            'metadata' => $metadata,
        ]);

        $body = $response->json() ?: [];

        return [
            'ok' => $response->successful() && (int) ($body['status'] ?? 0) === 1,
            'authorization_url' => $body['data']['authorization_url'] ?? null,
            'gateway_reference' => $reference,
            'message' => (string) ($body['message'] ?? 'Payment could not be initialized.'),
        ];
    }

    /**
     * Confirm a transaction by our external reference. Moolre's `txstatus`: 1 = success, 2 = failed,
     * anything else = still pending. Amount is reported in major units, so we scale to minor.
     *
     * @return array{ok: bool, tx: array{status: string, amount_minor: int, currency: string, reference: string, topup_id: ?int}}
     */
    public function verify(PaymentGateway $gateway, string $reference): array
    {
        $response = $this->request($gateway)->post(self::STATUS_URL, [
            'type' => 1,
            'idtype' => 1,
            'id' => $reference,
            'accountnumber' => (string) $gateway->moolre_account_number,
        ]);

        $body = $response->json() ?: [];
        $ok = $response->successful() && (int) ($body['status'] ?? 0) === 1;
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return [
            'ok' => $ok,
            'tx' => [
                'status' => $this->mapStatus((int) ($data['txstatus'] ?? -1)),
                'amount_minor' => (int) round($this->reportedAmount($data) * 100),
                'currency' => strtoupper((string) ($data['currency'] ?? '')),
                'reference' => $reference,
                'topup_id' => null,
            ],
        ];
    }

    /**
     * Moolre webhooks authenticate with a shared secret in the body compared to the stored one.
     */
    public function webhookSecretIsValid(PaymentGateway $gateway, string $incomingSecret): bool
    {
        $configured = (string) $gateway->webhook_secret;

        return $configured !== '' && $incomingSecret !== '' && hash_equals($configured, $incomingSecret);
    }

    private function mapStatus(int $txStatus): string
    {
        return match ($txStatus) {
            1 => 'success',
            2 => 'failed',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reportedAmount(array $data): float
    {
        foreach (['value', 'amount'] as $key) {
            if (isset($data[$key]) && (float) $data[$key] > 0) {
                return (float) $data[$key];
            }
        }

        return 0.0;
    }

    private function request(PaymentGateway $gateway): PendingRequest
    {
        $request = Http::withHeaders([
            'X-API-USER' => trim((string) $gateway->moolre_username),
            'X-API-PUBKEY' => trim((string) $gateway->public_key),
        ])->connectTimeout(10)->timeout(30)->acceptJson();

        // Local dev over WAMP/self-signed hosts often lacks a CA bundle; relax verification there
        // only, never in production. Live traffic keeps strict SSL.
        if (app()->environment('local')) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }
}
