import { Head, Link, router } from "@inertiajs/react";
import { Wallet as WalletIcon } from "lucide-react";
import { Amount } from "@/components/common/amount";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cedis } from "@/lib/format";
import { cn } from "@/lib/utils";
import { transactions as transactionsRoute } from "@/routes/agent";

interface Txn {
    id: number;
    type: string;
    direction: "credit" | "debit";
    source: "user" | "admin";
    amount: number;
    orderReference: string | null;
    paymentSource: string | null;
    status: string;
    balanceBefore: number;
    balanceAfter: number;
    code: string | null;
    date: string | null;
}

interface Props {
    balance: number;
    transactions: { data: Txn[]; links: PageLink[] };
    filters: { type: string; source: string };
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

const dash = <span className="text-muted-foreground">—</span>;

const columns: Column<Txn>[] = [
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
        render: (t) =>
            t.orderReference ? (
                <Link href={`/orders?q=${t.orderReference}`} className="text-sm font-medium text-brand hover:underline">
                    View
                </Link>
            ) : dash,
    },
];

export default function AgentTransactions({ balance, transactions, filters }: Props) {
    // Both selects reload the page carrying the other's value, so filtering by one never resets the other.
    const apply = (patch: { type?: string; source?: string }) =>
        router.get(
            transactionsRoute.url(),
            { type: filters.type, source: filters.source, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="Transactions" description="Your balance and full transaction history." />

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
        </>
    );
}

AgentTransactions.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Transactions", href: "/transactions" },
    ],
};
