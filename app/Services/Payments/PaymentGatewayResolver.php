<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;

/**
 * Routes a payment to Paystack or Moolre (both may be active at once):
 *   • Public checkout  → Paystack preferred, Moolre only as fallback.
 *   • Agent top-up     → Paystack below GHS 1,500, Moolre from GHS 1,500 up.
 * If the preferred gateway isn't usable, the other is tried; with Paystack off, all fall to Moolre.
 */
class PaymentGatewayResolver
{
    public const AGENT_TOPUP_MOOLRE_THRESHOLD_GHS = 1500.0;

    /** @var list<string> */
    private const CHECKOUT_ORDER = [PaymentGateway::PAYSTACK, PaymentGateway::MOOLRE];

    public function resolveForAgentTopup(float $amountGhs): string
    {
        $preferred = $amountGhs < self::AGENT_TOPUP_MOOLRE_THRESHOLD_GHS
            ? PaymentGateway::PAYSTACK
            : PaymentGateway::MOOLRE;

        if ($this->isUsable($preferred)) {
            return $preferred;
        }

        $alternate = $preferred === PaymentGateway::PAYSTACK ? PaymentGateway::MOOLRE : PaymentGateway::PAYSTACK;

        return $this->isUsable($alternate) ? $alternate : $preferred;
    }

    public function resolveForPublicCheckout(): string
    {
        foreach (self::CHECKOUT_ORDER as $gateway) {
            if ($this->isUsable($gateway)) {
                return $gateway;
            }
        }

        return PaymentGateway::PAYSTACK;
    }

    /**
     * A gateway is usable when it's active and carries the credentials its integration needs.
     */
    public function isUsable(string $gateway): bool
    {
        $row = PaymentGateway::forGateway($gateway);
        if ($row === null || ! $row->is_active) {
            return false;
        }

        return match ($gateway) {
            PaymentGateway::PAYSTACK => (string) $row->public_key !== '' && (string) $row->secret_key !== '',
            PaymentGateway::MOOLRE => (string) $row->public_key !== ''
                && (string) $row->moolre_username !== ''
                && (string) $row->moolre_account_number !== '',
            default => false,
        };
    }

    public function agentTopupRoutingLabel(): string
    {
        $paystack = $this->isUsable(PaymentGateway::PAYSTACK);
        $moolre = $this->isUsable(PaymentGateway::MOOLRE);
        $threshold = number_format(self::AGENT_TOPUP_MOOLRE_THRESHOLD_GHS, 0);

        return match (true) {
            $paystack && $moolre => "Automatic: Paystack below GHS {$threshold} · Moolre from GHS {$threshold}",
            $moolre => 'Moolre (all amounts)',
            $paystack => 'Paystack (all amounts)',
            default => 'No payment gateway configured',
        };
    }

    public function publicCheckoutRoutingLabel(): string
    {
        return $this->anyPublicCheckoutGatewayUsable() ? strtoupper($this->resolveForPublicCheckout()) : 'Not configured';
    }

    public function anyPublicCheckoutGatewayUsable(): bool
    {
        return $this->isUsable(PaymentGateway::PAYSTACK) || $this->isUsable(PaymentGateway::MOOLRE);
    }
}
