import { Head, useForm } from '@inertiajs/react';
import { updateMoolre, updatePaystack } from '@/actions/App/Http/Controllers/Admin/PaymentConfigController';
import { PageHeader } from '@/components/common/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Paystack {
    isActive: boolean;
    isLive: boolean;
    publicKey: string;
    currency: string;
    minTopup: number;
    maxTopup: number;
    chargePercent: number;
    hasSecret: boolean;
    hasWebhookSecret: boolean;
}

interface Moolre {
    isActive: boolean;
    publicKey: string;
    currency: string;
    moolreUsername: string;
    moolreAccountNumber: string;
    hasWebhookSecret: boolean;
}

interface Props {
    paystack: Paystack;
    moolre: Moolre;
    routing: { publicCheckout: string; agentTopup: string; moolreThreshold: number };
    webhooks: { paystack: string; moolre: string };
}

function Toggle({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="size-4 rounded border-border accent-brand" />
            {label}
        </label>
    );
}

export default function PaymentConfig({ paystack, moolre, routing, webhooks }: Props) {
    const paystackForm = useForm({
        public_key: paystack.publicKey,
        secret_key: '',
        webhook_secret: '',
        is_active: paystack.isActive,
        is_live: paystack.isLive,
        currency: paystack.currency,
        min_topup: paystack.minTopup,
        max_topup: paystack.maxTopup,
        charge_percent: paystack.chargePercent,
    });

    const moolreForm = useForm({
        public_key: moolre.publicKey,
        webhook_secret: '',
        is_active: moolre.isActive,
        currency: moolre.currency,
        moolre_username: moolre.moolreUsername,
        moolre_account_number: moolre.moolreAccountNumber,
    });

    return (
        <>
            <Head title="Admin — Payments" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader title="Payments" description="Payment-gateway credentials and routing. Secrets are encrypted and never shown." />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Routing</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-lg border border-border p-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Public checkout</p>
                            <p className="mt-1 text-sm">{routing.publicCheckout}</p>
                        </div>
                        <div className="rounded-lg border border-border p-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Agent top-ups</p>
                            <p className="mt-1 text-sm">{routing.agentTopup}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Paystack</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Public checkout, and agent top-ups below GHS {routing.moolreThreshold.toLocaleString()}.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-lg space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                paystackForm.put(updatePaystack().url, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        paystackForm.setData('secret_key', '');
                                        paystackForm.setData('webhook_secret', '');
                                    },
                                });
                            }}
                        >
                            <div className="space-y-1.5">
                                <Label>Public key</Label>
                                <Input value={paystackForm.data.public_key} onChange={(e) => paystackForm.setData('public_key', e.target.value)} placeholder="pk_..." />
                                {paystackForm.errors.public_key ? <p className="text-xs text-danger">{paystackForm.errors.public_key}</p> : null}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Secret key</Label>
                                <Input
                                    type="password"
                                    value={paystackForm.data.secret_key}
                                    onChange={(e) => paystackForm.setData('secret_key', e.target.value)}
                                    placeholder={paystack.hasSecret ? '•••••••• (leave blank to keep)' : 'sk_...'}
                                />
                                {paystackForm.errors.secret_key ? <p className="text-xs text-danger">{paystackForm.errors.secret_key}</p> : null}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Webhook secret</Label>
                                <Input
                                    type="password"
                                    value={paystackForm.data.webhook_secret}
                                    onChange={(e) => paystackForm.setData('webhook_secret', e.target.value)}
                                    placeholder={paystack.hasWebhookSecret ? '•••••••• (leave blank to keep)' : 'Optional'}
                                />
                                <p className="text-xs text-muted-foreground">Webhook URL: <span className="font-mono">{webhooks.paystack}</span></p>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div className="space-y-1.5">
                                    <Label>Currency</Label>
                                    <Input value={paystackForm.data.currency} onChange={(e) => paystackForm.setData('currency', e.target.value)} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Min top-up</Label>
                                    <Input type="number" step="0.01" value={paystackForm.data.min_topup} onChange={(e) => paystackForm.setData('min_topup', Number(e.target.value))} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Max top-up</Label>
                                    <Input type="number" step="0.01" value={paystackForm.data.max_topup} onChange={(e) => paystackForm.setData('max_topup', Number(e.target.value))} />
                                </div>
                            </div>
                            {paystackForm.errors.max_topup ? <p className="text-xs text-danger">{paystackForm.errors.max_topup}</p> : null}

                            <div className="space-y-1.5">
                                <Label>Charge percent (payer covers the fee)</Label>
                                <Input type="number" step="0.01" value={paystackForm.data.charge_percent} onChange={(e) => paystackForm.setData('charge_percent', Number(e.target.value))} />
                            </div>

                            <div className="flex gap-6">
                                <Toggle checked={paystackForm.data.is_active} onChange={(v) => paystackForm.setData('is_active', v)} label="Active" />
                                <Toggle checked={paystackForm.data.is_live} onChange={(v) => paystackForm.setData('is_live', v)} label="Live mode" />
                            </div>

                            <Button type="submit" disabled={paystackForm.processing}>Save Paystack</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Moolre</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Agent top-ups from GHS {routing.moolreThreshold.toLocaleString()} upward.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-lg space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                moolreForm.put(updateMoolre().url, {
                                    preserveScroll: true,
                                    onSuccess: () => moolreForm.setData('webhook_secret', ''),
                                });
                            }}
                        >
                            <div className="space-y-1.5">
                                <Label>Username</Label>
                                <Input value={moolreForm.data.moolre_username} onChange={(e) => moolreForm.setData('moolre_username', e.target.value)} />
                                {moolreForm.errors.moolre_username ? <p className="text-xs text-danger">{moolreForm.errors.moolre_username}</p> : null}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Public key</Label>
                                <Input value={moolreForm.data.public_key} onChange={(e) => moolreForm.setData('public_key', e.target.value)} />
                                {moolreForm.errors.public_key ? <p className="text-xs text-danger">{moolreForm.errors.public_key}</p> : null}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Account number</Label>
                                <Input value={moolreForm.data.moolre_account_number} onChange={(e) => moolreForm.setData('moolre_account_number', e.target.value)} />
                                {moolreForm.errors.moolre_account_number ? <p className="text-xs text-danger">{moolreForm.errors.moolre_account_number}</p> : null}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Webhook secret</Label>
                                <Input
                                    type="password"
                                    value={moolreForm.data.webhook_secret}
                                    onChange={(e) => moolreForm.setData('webhook_secret', e.target.value)}
                                    placeholder={moolre.hasWebhookSecret ? '•••••••• (leave blank to keep)' : 'Optional'}
                                />
                                <p className="text-xs text-muted-foreground">Webhook URL: <span className="font-mono">{webhooks.moolre}</span></p>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Currency</Label>
                                <Input value={moolreForm.data.currency} onChange={(e) => moolreForm.setData('currency', e.target.value)} className="w-32" />
                            </div>

                            <Toggle checked={moolreForm.data.is_active} onChange={(v) => moolreForm.setData('is_active', v)} label="Active" />

                            <Button type="submit" disabled={moolreForm.processing}>Save Moolre</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
