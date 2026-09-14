<?php

namespace App\Services\Payments;

use App\Models\BalanceTopup;
use App\Models\PaymentGateway;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Starts a real wallet top-up: resolves the gateway, records a PENDING BalanceTopup, and asks the
 * gateway to open a checkout. No money moves here — the wallet is credited only after the gateway
 * confirms the charge (see WalletTopupConfirmer). The agent enters the amount they want CREDITED;
 * we bill them the gross (base + gateway fee) so the wallet nets exactly that amount.
 */
class WalletTopupService
{
    public function __construct(
        private readonly PaymentGatewayResolver $resolver,
        private readonly PaystackClient $paystack,
        private readonly MoolreClient $moolre,
    ) {}

    /**
     * @return array{authorization_url: string, reference: string}
     *
     * @throws TopupException
     */
    public function initiate(Wallet $wallet, string $email, float $amount, string $callbackUrl): array
    {
        $amount = round($amount, 2);
        $gatewayName = $this->resolver->resolveForAgentTopup($amount);

        $gateway = PaymentGateway::forGateway($gatewayName);
        if ($gateway === null || ! $this->resolver->isUsable($gatewayName)) {
            throw new TopupException('Wallet top-ups are temporarily unavailable. Please try again later.');
        }

        $this->assertWithinLimits($gateway, $amount);
        $this->guardAgainstDoubleSubmit($wallet, $amount, $email);

        $gross = $gateway->grossFromBase($amount);
        $reference = 'MTP_'.time().'_'.$wallet->id.'_'.Str::lower(Str::random(6));

        $topup = BalanceTopup::query()->create([
            'wallet_id' => $wallet->id,
            'gateway' => $gatewayName,
            'reference' => $reference,
            'amount' => $amount,
            'charged_amount' => $gross,
            'currency' => $gateway->currency ?: 'GHS',
            'status' => BalanceTopup::STATUS_PENDING,
        ]);

        $result = $this->openCheckout($gatewayName, $gateway, $email, $gross, $callbackUrl, $topup);

        if ($result['gateway_reference'] !== null) {
            $topup->gateway_reference = $result['gateway_reference'];
            $topup->save();
        }

        if (! $result['ok'] || ! $result['authorization_url']) {
            $topup->update(['status' => BalanceTopup::STATUS_FAILED]);

            throw new TopupException($result['message'] ?: 'Payment could not be started. Please try again.');
        }

        return ['authorization_url' => $result['authorization_url'], 'reference' => $reference];
    }

    /**
     * @return array{ok: bool, authorization_url: ?string, gateway_reference: ?string, message: string}
     */
    private function openCheckout(string $gatewayName, PaymentGateway $gateway, string $email, float $gross, string $callbackUrl, BalanceTopup $topup): array
    {
        $reference = $topup->reference;
        $metadata = ['type' => 'wallet_topup', 'topup_id' => $topup->id, 'wallet_id' => $topup->wallet_id];

        if ($gatewayName === PaymentGateway::MOOLRE) {
            // Moolre confirms by our reference on return; embed it so the callback always has it.
            $redirect = $callbackUrl.(str_contains($callbackUrl, '?') ? '&' : '?').'reference='.urlencode($reference);

            return $this->moolre->initialize($gateway, $email, $gross, $reference, $redirect, $metadata);
        }

        return $this->paystack->initialize($gateway, $email, $gross, $reference, $callbackUrl, $metadata);
    }

    private function assertWithinLimits(PaymentGateway $gateway, float $amount): void
    {
        $min = (float) $gateway->min_topup;
        $max = (float) $gateway->max_topup;

        if ($min > 0 && $amount < $min) {
            throw new TopupException('The minimum top-up is GHS '.number_format($min, 2).'.');
        }
        if ($max > 0 && $amount > $max) {
            throw new TopupException('The maximum top-up is GHS '.number_format($max, 2).'.');
        }
    }

    /**
     * A short-lived cache reservation so a double-clicked submit doesn't open two checkouts.
     */
    private function guardAgainstDoubleSubmit(Wallet $wallet, float $amount, string $email): void
    {
        $key = sprintf('topup:%d:%0.2f:%s', $wallet->id, $amount, Str::lower(trim($email)));

        if (! Cache::add('idem:'.$key, true, now()->addSeconds(120))) {
            throw new TopupException('A similar top-up is already being processed. Please wait a moment.');
        }
    }
}
