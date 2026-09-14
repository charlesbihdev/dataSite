<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for Paystack's transaction API (initialize + verify) and webhook signature
 * checks. One method per endpoint, explicit timeouts, secrets read from the encrypted
 * `payment_gateways` row — never from the browser. Amounts cross the wire in kobo (minor units).
 */
class PaystackClient
{
    private const BASE_URL = 'https://api.paystack.co';

    /**
     * Ask Paystack to open a checkout for a gross amount and return where to send the payer.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{ok: bool, authorization_url: ?string, gateway_reference: ?string, message: string}
     */
    public function initialize(PaymentGateway $gateway, string $email, float $grossAmount, string $reference, string $callbackUrl, array $metadata = []): array
    {
        $response = $this->request($gateway)->post(self::BASE_URL.'/transaction/initialize', [
            'email' => $email,
            'amount' => (int) round($grossAmount * 100),
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        $body = $response->json() ?: [];

        return [
            'ok' => $response->successful() && (bool) ($body['status'] ?? false),
            'authorization_url' => $body['data']['authorization_url'] ?? null,
            'gateway_reference' => $body['data']['reference'] ?? null,
            'message' => (string) ($body['message'] ?? 'Payment could not be initialized.'),
        ];
    }

    /**
     * Independently confirm a transaction with Paystack (never trust the browser redirect).
     *
     * @return array{ok: bool, tx: array{status: string, amount_minor: int, currency: string, reference: string, topup_id: ?int}}
     */
    public function verify(PaymentGateway $gateway, string $reference): array
    {
        $response = $this->request($gateway)->get(self::BASE_URL.'/transaction/verify/'.urlencode($reference));
        $body = $response->json() ?: [];
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return [
            'ok' => $response->successful() && (bool) ($body['status'] ?? false),
            'tx' => $this->normalize($data),
        ];
    }

    /**
     * Map a Paystack transaction object (from verify or a webhook `data`) to the normalized shape
     * WalletTopupConfirmer consumes. Paystack amounts are already in minor units (kobo).
     *
     * @param  array<string, mixed>  $data
     * @return array{status: string, amount_minor: int, currency: string, reference: string, topup_id: ?int}
     */
    public function normalize(array $data): array
    {
        $metadata = $this->normalizeMetadata($data['metadata'] ?? null);

        return [
            'status' => strtolower((string) ($data['status'] ?? '')),
            'amount_minor' => (int) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'reference' => (string) ($data['reference'] ?? ''),
            'topup_id' => isset($metadata['topup_id']) ? (int) $metadata['topup_id'] : null,
        ];
    }

    /**
     * Paystack sends metadata as an array on verify but sometimes as a JSON string on webhooks.
     *
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Validate a webhook body against the gateway's HMAC-SHA512 signature. Prefers the dedicated
     * webhook secret, falling back to the secret key (Paystack signs with the secret key).
     */
    public function signatureIsValid(PaymentGateway $gateway, string $rawPayload, string $signature): bool
    {
        $secret = (string) ($gateway->webhook_secret ?: $gateway->secret_key);
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawPayload, $secret), $signature);
    }

    private function request(PaymentGateway $gateway): PendingRequest
    {
        $request = Http::withToken((string) $gateway->secret_key)
            ->connectTimeout(10)
            ->timeout(30)
            ->acceptJson();

        // Local dev over WAMP/self-signed hosts often lacks a CA bundle; relax verification there
        // only, never in production. Live traffic keeps strict SSL.
        if (app()->environment('local')) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }
}
