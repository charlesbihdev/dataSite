<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Storefront\CheckoutException;

/**
 * Opens a payment-gateway checkout for an online (storefront) order and records which gateway took
 * it. The order's own reference is used as the gateway reference, so the callback and webhook can
 * find the order again and {@see PaymentVerifier} can confirm the exact amount later. The customer
 * pays the retail price as-is (no fee mark-up — that's absorbed, unlike wallet top-ups).
 */
class OrderPaymentInitiator
{
    public function __construct(
        private readonly PaymentGatewayResolver $resolver,
        private readonly PaystackClient $paystack,
        private readonly MoolreClient $moolre,
    ) {}

    /**
     * @return array{authorization_url: string, gateway: string}
     *
     * @throws CheckoutException when no gateway is usable or the gateway rejects the checkout
     */
    public function initiate(Order $order, string $callbackUrl): array
    {
        $gatewayName = $this->resolver->resolveForPublicCheckout();
        $gateway = PaymentGateway::forGateway($gatewayName);

        if ($gateway === null || ! $this->resolver->isUsable($gatewayName)) {
            throw new CheckoutException('Online payment is unavailable right now. Please try again later.');
        }

        $amount = (float) $order->customer_price;
        $email = $this->resolveEmail($order);
        $metadata = ['type' => 'storefront_order', 'order_id' => $order->id];

        $result = $gatewayName === PaymentGateway::MOOLRE
            ? $this->moolre->initialize($gateway, $email, $amount, $order->reference, $this->withReference($callbackUrl, $order->reference), $metadata)
            : $this->paystack->initialize($gateway, $email, $amount, $order->reference, $callbackUrl, $metadata);

        $order->update(['gateway' => $gatewayName, 'gateway_reference' => $result['gateway_reference'] ?? $order->reference]);

        if (! $result['ok'] || ! $result['authorization_url']) {
            throw new CheckoutException($result['message'] ?: 'Payment could not be started. Please try again.');
        }

        return ['authorization_url' => $result['authorization_url'], 'gateway' => $gatewayName];
    }

    private function withReference(string $url, string $reference): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'reference='.urlencode($reference);
    }

    /**
     * The storefront collects only the receiver's phone, but gateways require an email to open a
     * checkout. Synthesize a syntactically valid one from the phone + a real domain (never the raw
     * app host, which is "localhost"/an IP in dev and gets rejected as an invalid email).
     */
    private function resolveEmail(Order $order): string
    {
        if ($order->customer_email && filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)) {
            return $order->customer_email;
        }

        $digits = preg_replace('/\D+/', '', (string) $order->beneficiary_phone) ?: 'customer';
        $candidate = $digits.'@'.$this->emailDomain();

        return filter_var($candidate, FILTER_VALIDATE_EMAIL) ? $candidate : 'customer@example.com';
    }

    private function emailDomain(): string
    {
        // Prefer the configured mail-from domain, then a real app host; fall back to a reserved,
        // always-valid placeholder so the gateway never rejects the synthesized address.
        $from = (string) config('mail.from.address');
        $fromDomain = str_contains($from, '@') ? substr(strrchr($from, '@'), 1) : '';
        if ($fromDomain !== '' && str_contains($fromDomain, '.')) {
            return $fromDomain;
        }

        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($appHost !== '' && str_contains($appHost, '.') && ! filter_var($appHost, FILTER_VALIDATE_IP)) {
            return $appHost;
        }

        return 'example.com';
    }
}
