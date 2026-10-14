import { Head, router, useForm } from "@inertiajs/react";
import { AlertTriangle } from "lucide-react";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { SimplePagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cedis } from "@/lib/format";
import { cancel, store } from "@/routes/subagent/withdrawals";

interface Method { key: string; label: string; min: number; enabled: boolean }
interface Wd {
    id: number;
    method: string;
    network?: string | null;
    destination?: string | null;
    accountName?: string | null;
    amount: number;
    status: string;
    date: string | null;
    canCancel: boolean;
}
interface Props {
    stats: { totalEarnings: number; available: number; pending: number; withdrawn: number };
    methods: Method[];
    limits: { min: number; max: number | null };
    withdrawals: { data: Wd[]; from: number | null; prev_page_url: string | null; next_page_url: string | null };
}

export default function SubagentWithdrawals({ stats, methods, limits, withdrawals }: Props) {
    const form = useForm({
        method: "",
        network: "mtn",
        destination: "",
        account_name: "",
        amount: "",
    });
    const eligible = methods.some((m) => m.enabled);
    const selected = methods.find((m) => m.key === form.data.method);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(store.url(), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const columns: Column<Wd>[] = [
        { key: "row", header: "#", render: (w) => <span className="text-muted-foreground">{(withdrawals.from ?? 1) + withdrawals.data.indexOf(w)}</span> },
        {
            key: "destination",
            header: "Recipient",
            render: (w) => (
                <div className="flex flex-col">
                    <span className="font-medium text-foreground">{w.accountName || w.destination || w.method}</span>
                    <span className="text-xs text-muted-foreground font-mono">
                        {w.network ? w.network.toUpperCase() + " · " : ""}{w.destination || ""}
                    </span>
                </div>
            ),
        },
        { key: "amount", header: "Amount", align: "right", render: (w) => <span className="tabular-nums font-medium">{cedis(w.amount)}</span> },
        { key: "status", header: "Status", render: (w) => <StatusBadge status={w.status} /> },
        { key: "date", header: "Date", render: (w) => <span className="text-muted-foreground">{w.date ?? "—"}</span> },
        {
            key: "actions",
            header: "Actions",
            align: "right",
            render: (w) =>
                w.canCancel ? (
                    <Button variant="ghost" size="sm" className="text-danger hover:text-danger" onClick={() => router.post(cancel(w.id).url, {}, { preserveScroll: true })}>
                        Cancel
                    </Button>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <>
            <Head title="Withdrawal" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="Withdrawal" description="Withdraw your matured earnings and track past requests." />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile label="Total Earnings" value={cedis(stats.totalEarnings)} hint="Your commission (lifetime)" />
                    <StatTile label="Available Balance" value={cedis(stats.available)} hint="Ready to withdraw" />
                    <StatTile label="Pending Withdrawal" value={cedis(stats.pending)} hint="Awaiting payout" />
                    <StatTile label="Withdrawn" value={cedis(stats.withdrawn)} hint="Paid out" />
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-5">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base">Request Withdrawal</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {!eligible && (
                            <div className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm">
                                <p className="flex items-center gap-2 font-semibold text-warning-foreground">
                                    <AlertTriangle className="size-4" /> Rewards Not Matured!
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    Your available balance ({cedis(stats.available)}) has not reached the minimum for any withdrawal method:
                                </p>
                                <ul className="mt-2 space-y-0.5 text-muted-foreground">
                                    {methods.map((m) => (
                                        <li key={m.key}>
                                            <span className="font-medium uppercase text-foreground">{m.label}</span>: {cedis(m.min)} minimum
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-2 text-muted-foreground">Keep selling through your storefront to reach the threshold!</p>
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label>Withdrawal Type *</Label>
                                <Select value={form.data.method} onValueChange={(v) => form.setData("method", v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {methods.map((m) => (
                                            <SelectItem key={m.key} value={m.key} disabled={!m.enabled}>
                                                {m.label}{!m.enabled ? ` (min ${cedis(m.min)})` : ""}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">Only withdrawal types you qualify for are enabled.</p>
                                {form.errors.method && <p className="text-xs text-destructive">{form.errors.method}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="amount">Amount (GHS) *</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min={limits.min}
                                    max={limits.max ?? undefined}
                                    placeholder="Enter amount"
                                    value={form.data.amount}
                                    onChange={(e) => form.setData("amount", e.target.value)}
                                    disabled={!eligible}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Available: {cedis(stats.available)} · Min {cedis(limits.min)}{limits.max !== null ? ` · Max ${cedis(limits.max)}` : ""}
                                </p>
                                {form.errors.amount && <p className="text-xs text-destructive">{form.errors.amount}</p>}
                            </div>

                            {selected && selected.key === "momo" && (
                                <>
                                    <div className="space-y-1.5">
                                        <Label>Mobile Money Network *</Label>
                                        <Select value={form.data.network} onValueChange={(v) => form.setData("network", v)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select network" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="mtn">MTN Mobile Money</SelectItem>
                                                <SelectItem value="telecel">Telecel Cash</SelectItem>
                                                <SelectItem value="at">AT Money</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {form.errors.network && <p className="text-xs text-destructive">{form.errors.network}</p>}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="destination">Mobile Money Number *</Label>
                                        <Input
                                            id="destination"
                                            placeholder="0241234567"
                                            value={form.data.destination}
                                            onChange={(e) => form.setData("destination", e.target.value)}
                                        />
                                        {form.errors.destination && <p className="text-xs text-destructive">{form.errors.destination}</p>}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="account_name">Account Name (Registered MoMo Name) *</Label>
                                        <Input
                                            id="account_name"
                                            placeholder="e.g. Ama Serwaa"
                                            value={form.data.account_name}
                                            onChange={(e) => form.setData("account_name", e.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground">The exact name on this Mobile Money SIM card for payout verification.</p>
                                        {form.errors.account_name && <p className="text-xs text-destructive">{form.errors.account_name}</p>}
                                    </div>
                                </>
                            )}

                            {selected && selected.key !== "momo" && (
                                <div className="space-y-1.5">
                                    <Label htmlFor="destination">Account details *</Label>
                                    <Input
                                        id="destination"
                                        placeholder="Bank / account details"
                                        value={form.data.destination}
                                        onChange={(e) => form.setData("destination", e.target.value)}
                                    />
                                    <p className="text-xs text-muted-foreground">Minimum {cedis(selected.min)} for {selected.label}.</p>
                                    {form.errors.destination && <p className="text-xs text-destructive">{form.errors.destination}</p>}
                                </div>
                            )}

                            {!selected && (
                                <p className="text-xs text-muted-foreground">Select a withdrawal type to see requirements.</p>
                            )}

                            <Button type="submit" disabled={!eligible || !form.data.method || form.processing}>
                                Request Withdrawal
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="min-w-0 rounded-xl border border-border bg-card shadow-sm lg:col-span-3">
                    <div className="border-b border-border p-4">
                        <h2 className="text-sm font-semibold">Withdrawal History</h2>
                    </div>
                    <DataTable columns={columns} rows={withdrawals.data} rowKey={(w) => w.id} emptyMessage="No withdrawal requests yet." />
                    {(withdrawals.prev_page_url || withdrawals.next_page_url) && (
                        <div className="border-t border-border p-4">
                            <SimplePagination prevUrl={withdrawals.prev_page_url} nextUrl={withdrawals.next_page_url} />
                        </div>
                    )}
                </div>
                </div>
            </div>
        </>
    );
}

SubagentWithdrawals.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Withdrawal", href: "/withdrawals" },
    ],
};
