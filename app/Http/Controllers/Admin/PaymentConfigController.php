<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoolreConfigRequest;
use App\Http\Requests\Admin\PaystackConfigRequest;
use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payment-gateway credentials + rules (Paystack, Moolre). Secrets are stored encrypted and never
 * sent to the client — the form shows only a "key is set" flag and keeps the stored value when the
 * field is left blank (mirrors the Databundleshub connection screen).
 */
class PaymentConfigController extends Controller
{
    public function index(PaymentGatewayResolver $resolver): Response
    {
        return Inertia::render('admin/payment-config', [
            'paystack' => $this->present(PaymentGateway::forGateway(PaymentGateway::PAYSTACK)),
            'moolre' => $this->present(PaymentGateway::forGateway(PaymentGateway::MOOLRE)),
            'routing' => [
                'publicCheckout' => $resolver->publicCheckoutRoutingLabel(),
                'agentTopup' => $resolver->agentTopupRoutingLabel(),
                'moolreThreshold' => PaymentGatewayResolver::AGENT_TOPUP_MOOLRE_THRESHOLD_GHS,
            ],
            'webhooks' => [
                'paystack' => url('/webhooks/paystack'),
                'moolre' => url('/webhooks/moolre'),
            ],
        ]);
    }

    public function updatePaystack(PaystackConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $row = PaymentGateway::forGateway(PaymentGateway::PAYSTACK) ?? new PaymentGateway(['gateway' => PaymentGateway::PAYSTACK]);

        $row->fill([
            'public_key' => $data['public_key'] ?? '',
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_live' => (bool) ($data['is_live'] ?? false),
            'currency' => $data['currency'],
            'min_topup' => $data['min_topup'],
            'max_topup' => $data['max_topup'],
            'charge_percent' => $data['charge_percent'],
        ]);
        $this->applySecret($row, 'secret_key', $data['secret_key'] ?? null);
        $this->applySecret($row, 'webhook_secret', $data['webhook_secret'] ?? null);
        $row->gateway = PaymentGateway::PAYSTACK;
        $row->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paystack configuration saved.']);

        return back();
    }

    public function updateMoolre(MoolreConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $row = PaymentGateway::forGateway(PaymentGateway::MOOLRE) ?? new PaymentGateway(['gateway' => PaymentGateway::MOOLRE]);

        $row->fill([
            'public_key' => $data['public_key'] ?? '',
            'is_active' => (bool) ($data['is_active'] ?? false),
            'currency' => $data['currency'],
            'moolre_username' => $data['moolre_username'] ?? '',
            'moolre_account_number' => $data['moolre_account_number'] ?? '',
        ]);
        $this->applySecret($row, 'webhook_secret', $data['webhook_secret'] ?? null);
        $row->gateway = PaymentGateway::MOOLRE;
        $row->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Moolre configuration saved.']);

        return back();
    }

    /**
     * A blank secret on update keeps the stored one; a new value replaces it.
     */
    private function applySecret(PaymentGateway $row, string $attribute, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $row->{$attribute} = $value;
        } elseif (! $row->exists) {
            $row->{$attribute} = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(?PaymentGateway $row): array
    {
        return [
            'isActive' => (bool) ($row?->is_active ?? false),
            'isLive' => (bool) ($row?->is_live ?? false),
            'publicKey' => $row?->public_key ?? '',
            'currency' => $row?->currency ?? 'GHS',
            'minTopup' => (float) ($row?->min_topup ?? 10),
            'maxTopup' => (float) ($row?->max_topup ?? 10000),
            'chargePercent' => (float) ($row?->charge_percent ?? 0),
            'moolreUsername' => $row?->moolre_username ?? '',
            'moolreAccountNumber' => $row?->moolre_account_number ?? '',
            'hasSecret' => $row !== null && (string) $row->secret_key !== '',
            'hasWebhookSecret' => $row !== null && (string) $row->webhook_secret !== '',
        ];
    }
}
