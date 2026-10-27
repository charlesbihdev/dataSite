import { Head, router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Banknote, Clock, Wallet as WalletIcon } from "lucide-react";
import { Amount } from "@/components/common/amount";
import { Column, DataTable } from "@/components/common/data-table";
import { DateRangePicker, DateRangeValue } from "@/components/common/date-range-picker";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { TopupDialog } from "@/components/common/topup-dialog";
import { VerifyTopupButton, WALLET_STATUS_FILTERS } from "@/components/common/verify-topup-button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cedis } from "@/lib/format";
import { cn } from "@/lib/utils";
import { topup, transactions as transactionsRoute } from "@/routes/subagent";
import { verify as verifyTopup } from "@/routes/subagent/topup";

type Tab = "earnings" | "wallet";

interface Earning {
    id: number;
    type: string;
    orderReference: string | null;
    bundle: string;
    amount: number;
    status: string;
    date: string | null;
}
interface WalletEntry {
    id: number;
    type: string;
    reference: string | null;
    amount: number;
    status: string;
    balanceAfter: number | null;
    // Set on gateway top-ups not yet credited (pending/failed) — the row offers Verify.
    topupId: number | null;
    date: string | null;
}
interface Props {
    tab: Tab;
    stats: { available: number; credited: number; pending: number; walletBalance: number };
    transactions: { data: (Earning | WalletEntry)[]; links: PageLink[] };
    filters: { status: string; type: string; q: string; range: string; from: string | null; to: string | null };
}

const TABS: { key: Tab; label: string }[] = [
    { key: "earnings", label: "Earnings" },
    { key: "wallet", label: "Wallet" },
];

const STATUS_FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All statuses" },
    { value: "pending", label: "Pending" },
    { value: "credited", label: "Credited" },
    { value: "reversed", label: "Reversed" },
];

const TYPE_FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All transactions" },
    { value: "topup", label: "Wallet top-up" },
    { value: "purchase", label: "Data purchase" },
    { value: "refund", label: "Refunds" },
];

const dash = <span className="text-muted-foreground">—</span>;

const earningColumns: Column<Earning>[] = [
    { key: "date", header: "Date", render: (e) => <span className="text-muted-foreground">{e.date ?? "—"}</span> },
    { key: "orderReference", header: "Order", render: (e) => (e.orderReference ? <span className="font-mono text-xs">{e.orderReference}</span> : dash) },
    { key: "bundle", header: "Bundle", render: (e) => <span className="uppercase">{e.bundle}</span> },
    { key: "type", header: "Type", render: (e) => <span className="font-medium">{e.type}</span> },
    { key: "amount", header: "Amount", align: "right", render: (e) => <Amount value={e.amount} signed /> },
    { key: "status", header: "Status", render: (e) => <StatusBadge status={e.status} /> },
];

const walletColumns: Column<WalletEntry>[] = [
    { key: "date", header: "Date", render: (t) => <span className="text-muted-foreground">{t.date ?? "—"}</span> },
    { key: "type", header: "Type", render: (t) => <span className="font-medium">{t.type}</span> },
    { key: "reference", header: "Reference", render: (t) => (t.reference ? <span className="font-mono text-xs">{t.reference}</span> : dash) },
    { key: "amount", header: "Amount", align: "right", render: (t) => <Amount value={t.amount} signed /> },
    { key: "status", header: "Status", render: (t) => <StatusBadge status={t.status} /> },
    { key: "balanceAfter", header: "Balance After", align: "right", render: (t) => (t.balanceAfter !== null ? <span className="tabular-nums">{cedis(t.balanceAfter)}</span> : dash) },
    {
        key: "action",
        header: "",
        align: "right",
        render: (t) => (t.topupId !== null ? <VerifyTopupButton topupId={t.topupId} verify={verifyTopup} /> : null),
    },
];

export default function SubagentTransactions({ tab, stats, transactions, filters }: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    const isWallet = tab === "wallet";

    // Every control reloads this route carrying the others' current values, so applying one never resets another.
    const apply = (patch: Record<string, string | null>) =>
        router.get(
            transactionsRoute.url(),
            {
                tab,
                status: filters.status,
                type: filters.type,
                q: search,
                range: filters.range,
                from: filters.from ?? null,
                to: filters.to ?? null,
                ...patch,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const applyRange = (next: DateRangeValue) =>
        apply({ range: next.range, from: next.from ?? null, to: next.to ?? null });

    useEffect(() => setSearch(filters.q ?? ""), [filters.q]);

    const onSearchChange = (value: string) => {
        setSearch(value);
        if (debounce.current) {
            clearTimeout(debounce.current);
        }
        debounce.current = setTimeout(() => apply({ q: value }), 400);
    };

    const selects = isWallet
        ? [
              { value: filters.type, key: "type", options: TYPE_FILTERS },
              { value: filters.status, key: "status", options: WALLET_STATUS_FILTERS },
          ]
        : [{ value: filters.status, key: "status", options: STATUS_FILTERS }];

    return (
        <>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="Transactions"
                    description={isWallet ? "Your wallet top-ups and order purchases." : "Your earnings from every storefront sale."}
                    actions={
                        <div className="flex items-center gap-2">
                            <DateRangePicker value={{ range: filters.range, from: filters.from, to: filters.to }} onChange={applyRange} />
                            {isWallet && <TopupDialog action={topup} />}
                        </div>
                    }
                />

                <div className="flex w-fit gap-1 rounded-lg border border-border p-1" role="tablist">
                    {TABS.map(({ key, label }) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={tab === key}
                            onClick={() => apply({ tab: key, q: "", status: "all", type: "all" })}
                            className={cn(
                                "rounded-md px-4 py-1.5 text-sm font-medium transition",
                                tab === key ? "bg-brand text-brand-fg" : "text-muted-foreground hover:bg-muted",
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {isWallet ? (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatTile
                            label="Wallet Balance"
                            value={cedis(stats.walletBalance)}
                            hint={<span className="inline-flex items-center gap-1"><WalletIcon className="size-3" /> Available for purchases</span>}
                        />
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatTile
                            label="Available Balance"
                            value={cedis(stats.available)}
                            hint={<span className="inline-flex items-center gap-1"><WalletIcon className="size-3" /> Ready to withdraw</span>}
                        />
                        <StatTile
                            label="Total Earned"
                            value={cedis(stats.credited)}
                            hint={<span className="inline-flex items-center gap-1"><Banknote className="size-3" /> Credited (lifetime)</span>}
                        />
                        <StatTile
                            label="Pending"
                            value={cedis(stats.pending)}
                            hint={<span className="inline-flex items-center gap-1"><Clock className="size-3" /> Awaiting delivery</span>}
                        />
                    </div>
                )}

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border p-4">
                        {selects.map((select) => (
                            <Select key={select.key} value={select.value} onValueChange={(v) => apply({ [select.key]: v })}>
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {select.options.map((f) => (
                                        <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ))}
                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder={isWallet ? "Search reference…" : "Search order or phone…"}
                            className="ml-auto w-full bg-background sm:w-72"
                        />
                    </div>
                    {isWallet ? (
                        <DataTable
                            columns={walletColumns}
                            rows={(transactions.data ?? []) as WalletEntry[]}
                            rowKey={(t) => (t.topupId !== null ? `topup-${t.topupId}` : t.id)}
                            emptyMessage="No wallet activity yet. Top up to start placing orders."
                        />
                    ) : (
                        <DataTable
                            columns={earningColumns}
                            rows={(transactions.data ?? []) as Earning[]}
                            rowKey={(e) => e.id}
                            emptyMessage="No earnings yet. Sales through your storefront will show here."
                        />
                    )}
                    {transactions.links.length > 3 && (
                        <div className="border-t border-border p-4">
                            <Pagination links={transactions.links} />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

SubagentTransactions.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Transactions", href: "/transactions" },
    ],
};
