<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;

/**
 * Single source of truth for payment fees and gross/base calculations across the application.
 * All payment initializations (wallet top-ups, storefront orders, etc.) use this service to compute
 * charges based on the admin-configured charge_percent.
 */
class PaymentFeeCalculator
{
    /**
     * Compute fee breakdown for a given base amount and gateway.
     *
     * @return array{
     *     base_amount: float,
     *     fee_amount: float,
     *     charge_percent: float,
     *     gross_amount: float
     * }
     */
    public function calculate(float $baseAmount, PaymentGateway|string|null $gateway): array
    {
        $gatewayModel = is_string($gateway)
            ? PaymentGateway::forGateway($gateway)
            : $gateway;

        $pct = (float) ($gatewayModel?->charge_percent ?? 0);
        $base = max(0.0, round($baseAmount, 2));
        $fee = $pct > 0 ? round($base * ($pct / 100), 2) : 0.0;
        $gross = round($base + $fee, 2);

        return [
            'base_amount' => $base,
            'fee_amount' => $fee,
            'charge_percent' => $pct,
            'gross_amount' => $gross,
        ];
    }

    /**
     * Get the gross amount to charge the payer (base amount + fee).
     */
    public function gross(float $baseAmount, PaymentGateway|string|null $gateway): float
    {
        return $this->calculate($baseAmount, $gateway)['gross_amount'];
    }

    /**
     * Get the fee amount only.
     */
    public function fee(float $baseAmount, PaymentGateway|string|null $gateway): float
    {
        return $this->calculate($baseAmount, $gateway)['fee_amount'];
    }

    /**
     * Reverse calculation: recover the base net amount from a verified gross amount.
     */
    public function baseFromGross(float $grossAmount, PaymentGateway|string|null $gateway): float
    {
        $gatewayModel = is_string($gateway)
            ? PaymentGateway::forGateway($gateway)
            : $gateway;

        $pct = (float) ($gatewayModel?->charge_percent ?? 0);

        if ($grossAmount <= 0) {
            return 0.0;
        }

        return round($pct > 0 ? $grossAmount / (1 + ($pct / 100)) : $grossAmount, 2);
    }
}

