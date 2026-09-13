import { Deferred, Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import { Check, Copy, Download, RefreshCw, Share2 } from "lucide-react";
import { generateQr, updateContact } from "@/actions/App/Http/Controllers/Agent/ReferralController";
import { Skeleton } from "@/components/ui/skeleton";
import { Amount } from "@/components/common/amount";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cedis } from "@/lib/format";

interface Pkg { id: number; network: string; capacityGb: number; price: number; profit: number }
interface Props {
    referralUrl: string;
    referralQr?: string; // deferred — undefined until it streams in
    contact: { store_name: string | null; whatsapp_number: string | null; whatsapp_group_link: string | null };
    stats: { clicks: number; sales: number; revenue: number; activePackages: number; conversion: number };
    packages: Pkg[];
}

const columns: Column<Pkg>[] = [
    { key: "network", header: "Network", render: (p) => <span className="font-medium uppercase">{p.network}</span> },
    { key: "package", header: "Package", render: (p) => `${p.capacityGb}GB` },
    { key: "price", header: "Your Price", align: "right", render: (p) => cedis(p.price) },
    { key: "profit", header: "Your Profit", align: "right", render: (p) => <Amount value={p.profit} /> },
    { key: "status", header: "Status", render: () => <StatusBadge status="active" /> },
];

export default function AgentReferral({ referralUrl, referralQr, contact, stats, packages }: Props) {
    const [copied, setCopied] = useState(false);
    const form = useForm({
        store_name: contact.store_name ?? "",
        whatsapp_number: contact.whatsapp_number ?? "",
        whatsapp_group_link: contact.whatsapp_group_link ?? "",
    });

    const copy = async () => {
        await navigator.clipboard.writeText(referralUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(updateContact.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="My Referral Link" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="My Referral Link" description="Share your store link and track how it performs." />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile label="Total Clicks" value={String(stats.clicks)} hint="Visits to your link" />
                    <StatTile label="Total Sales" value={String(stats.sales)} hint="Orders from your link" />
                    <StatTile label="Total Revenue" value={cedis(stats.revenue)} hint="Paid storefront orders" />
                    <StatTile label="Active Packages" value={String(stats.activePackages)} hint="Shown on your link" />
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle className="text-base">Your Referral Link</CardTitle>
                            <CardDescription>Share this link — customers buy directly from your store.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <Deferred data="referralQr" fallback={<Skeleton className="size-32 shrink-0 rounded-lg" />}>
                                <img src={referralQr} alt="Referral QR code" className="size-32 shrink-0 rounded-lg border border-border bg-white p-2" />
                            </Deferred>
                            <div className="min-w-0 flex-1 space-y-2">
                                <Label>Referral link</Label>
                                <Input readOnly value={referralUrl} className="bg-muted font-mono text-xs" onFocus={(e) => e.currentTarget.select()} />
                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={copy}>
                                        {copied ? <Check className="size-4 text-success" /> : <Copy className="size-4" />}
                                        {copied ? "Copied" : "Copy"}
                                    </Button>
                                    <Button asChild variant="outline" size="sm">
                                        <a href={`https://wa.me/?text=${encodeURIComponent(referralUrl)}`} target="_blank" rel="noreferrer">
                                            <Share2 className="size-4" /> Quick share
                                        </a>
                                    </Button>
                                    {referralQr && (
                                        <Button asChild variant="outline" size="sm">
                                            <a href={referralQr} download="referral-qr.png">
                                                <Download className="size-4" /> Download PNG
                                            </a>
                                        </Button>
                                    )}
                                    <Button type="button" variant="ghost" size="sm" onClick={() => router.post(generateQr.url(), {}, { preserveScroll: true })}>
                                        <RefreshCw className="size-4" /> Regenerate
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader><CardTitle className="text-base">Performance</CardTitle></CardHeader>
                        <CardContent>
                            <dl className="space-y-3 text-sm">
                                <Row label="Clicks" value={String(stats.clicks)} />
                                <Row label="Sales" value={String(stats.sales)} />
                                <Row label="Revenue" value={cedis(stats.revenue)} />
                                <Row label="Conversion" value={`${stats.conversion}%`} />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Referral Contact Details</CardTitle>
                        <CardDescription>Shown on your referral checkout page so customers can reach you for support.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={save} className="grid gap-4 sm:grid-cols-2">
                            <Field label="Store Name" hint="Displayed as your store name on checkout." error={form.errors.store_name}>
                                <Input placeholder="e.g. Kofi Data Store" value={form.data.store_name} onChange={(e) => form.setData("store_name", e.target.value)} />
                            </Field>
                            <Field label="WhatsApp Number" hint="Direct number for customer contact." error={form.errors.whatsapp_number}>
                                <Input placeholder="0548715098" value={form.data.whatsapp_number} onChange={(e) => form.setData("whatsapp_number", e.target.value)} />
                            </Field>
                            <Field label="WhatsApp Community/Group Link" hint="Link to your support group." error={form.errors.whatsapp_group_link}>
                                <Input placeholder="https://chat.whatsapp.com/..." value={form.data.whatsapp_group_link} onChange={(e) => form.setData("whatsapp_group_link", e.target.value)} />
                            </Field>
                            <div className="flex items-end sm:col-span-2">
                                <Button type="submit" disabled={form.processing}>Save details</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="border-b border-border p-4">
                        <h2 className="text-sm font-semibold">Packages Customers Will See</h2>
                    </div>
                    <DataTable columns={columns} rows={packages} rowKey={(p) => p.id} emptyMessage="No active packages. Add some in My Packages." />
                    <p className="border-t border-border p-4 text-xs text-muted-foreground">
                        💡 Add or remove packages in <span className="font-medium text-foreground">Packages</span> — your link updates automatically.
                    </p>
                </div>
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-semibold tabular-nums">{value}</dd>
        </div>
    );
}

function Field({ label, hint, error, children }: { label: string; hint: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            <p className="text-xs text-muted-foreground">{hint}</p>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

AgentReferral.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "My Referral Link", href: "/referral" },
    ],
};
