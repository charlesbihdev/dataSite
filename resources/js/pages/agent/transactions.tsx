import { Head, router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Wallet as WalletIcon } from "lucide-react";
import { TransactionDetailDialog, Txn } from "@/components/agent/transaction-detail-dialog";
import { TopupDialog } from "@/components/agent/topup-dialog";
import { Amount } from "@/components/common/amount";
import { Column, DataTable } from "@/components/common/data-table";
import { DateRangePicker, DateRangeValue } from "@/components/common/date-range-picker";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cedis } from "@/lib/format";
import { cn } from "@/lib/utils";
import { transactions as transactionsRoute } from "@/routes/agent";

interface Props {
    balance: number;
    transactions: { data: Txn[]; links: PageLink[] };
    filters: { type: string; source: string; payment: string; q: string; range: string; from: string | null; to: string | null };
}

const TYPE_FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All transactions" },
    { value: "topup", label: "Wallet top-up" },
    { value: "debit", label: "Wallet debit" },
    { value: "purchase", label: "Data purchase" },
];

const SOURCE_FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All sources" },
    { value: "user", label: "User" },
    { value: "admin", label: "Admin" },
];

const PAYMENT_FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All payment sources" },
    { value: "paystack", label: "Paystack" },
    { value: "wallet", label: "Wallet" },
];

const dash = <span className="text-muted-foreground">—</span>;

const makeColumns = (onView: (t: Txn) => void): Column<Txn>[] => [
    { key: "type", header: "Type", render: (t) => <span className="font-medium">{t.type}</span> },
    {
        key: "source",
        header: "Source",
        render: (t) => (
            <span className={cn(
                "inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase",
                t.source === "admin" ? "bg-info/10 text-info" : "bg-muted text-muted-foreground",
            )}>
                {t.source}
            </span>
        ),
    },
    { key: "amount", header: "Amount", align: "right", render: (t) => <Amount value={t.amount} signed /> },
    { key: "orderReference", header: "Order ID", render: (t) => (t.orderReference ? <span className="font-mono text-xs">{t.orderReference}</span> : dash) },
    { key: "paymentSource", header: "Payment Src", render: (t) => t.paymentSource ?? dash },
    { key: "status", header: "Status", render: (t) => <StatusBadge status={t.status} /> },
    { key: "balanceBefore", header: "Balance Before", align: "right", render: (t) => <span className="tabular-nums">{cedis(t.balanceBefore)}</span> },
    { key: "balanceAfter", header: "Balance After", align: "right", render: (t) => <span className="tabular-nums">{cedis(t.balanceAfter)}</span> },
    { key: "code", header: "Transaction Code", render: (t) => (t.code ? <span className="font-mono text-xs">{t.code}</span> : dash) },
    { key: "date", header: "Date", render: (t) => <span className="text-muted-foreground">{t.date ?? "—"}</span> },
    {
        key: "action",
        header: "Action",
        align: "right",
        render: (t) => (
            <Button variant="ghost" size="sm" onClick={() => onView(t)}>
                View
            </Button>
        ),
    },
];

export default function AgentTransactions({ balance, transactions, filters }: Props) {
    const [selected, setSelected] = useState<Txn | null>(null);
    const [search, setSearch] = useState(filters.q ?? "");
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    const columns = makeColumns(setSelected);

    // Every control reloads this route carrying the others' current values, so applying one never resets another.
    const apply = (patch: Record<string, string | null>) =>
        router.get(
            transactionsRoute.url(),
            {
                type: filters.type,
                source: filters.source,
                payment: filters.payment,
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

    return (
        <>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="Transactions"
                    description="Your balance and full transaction history."
                    actions={
                        <div className="flex items-center gap-2">
                            <DateRangePicker value={{ range: filters.range, from: filters.from, to: filters.to }} onChange={applyRange} />
                            <TopupDialog />
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatTile
                        label="Wallet Balance"
                        value={cedis(balance)}
                        hint={<span className="inline-flex items-center gap-1"><WalletIcon className="size-3" /> Available for purchases</span>}
                    />
                </div>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    {/* Filter bar — scopes the table below only. */}
                    <div className="flex flex-wrap items-center gap-2 border-b border-border p-4">
                        <Select value={filters.source} onValueChange={(v) => apply({ source: v })}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {SOURCE_FILTERS.map((f) => (
                                    <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={filters.type} onValueChange={(v) => apply({ type: v })}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TYPE_FILTERS.map((f) => (
                                    <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={filters.payment} onValueChange={(v) => apply({ payment: v })}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PAYMENT_FILTERS.map((f) => (
                                    <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Search code, description, or type…"
                            className="ml-auto w-full bg-background sm:w-80"
                        />
                    </div>
                    <DataTable
                        columns={columns}
                        rows={transactions.data ?? []}
                        rowKey={(t) => t.id}
                        emptyMessage="No transactions yet."
                    />
                    {transactions.links.length > 3 && (
                        <div className="border-t border-border p-4">
                            <Pagination links={transactions.links} />
                        </div>
                    )}
                </div>
            </div>

            <TransactionDetailDialog transaction={selected} onClose={() => setSelected(null)} />
        </>
    );
}

AgentTransactions.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Transactions", href: "/transactions" },
    ],
};
