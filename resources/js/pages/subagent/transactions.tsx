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
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cedis } from "@/lib/format";
import { transactions as transactionsRoute } from "@/routes/subagent";

interface Earning {
    id: number;
    type: string;
    orderReference: string | null;
    bundle: string;
    amount: number;
    status: string;
    date: string | null;
}
interface Props {
    stats: { available: number; credited: number; pending: number };
    transactions: { data: Earning[]; links: PageLink[] };
    filters: { status: string; q: string; range: string; from: string | null; to: string | null };
}

const STATUS_FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All statuses" },
    { value: "pending", label: "Pending" },
    { value: "credited", label: "Credited" },
    { value: "reversed", label: "Reversed" },
];

const dash = <span className="text-muted-foreground">—</span>;

const columns: Column<Earning>[] = [
    { key: "date", header: "Date", render: (e) => <span className="text-muted-foreground">{e.date ?? "—"}</span> },
    { key: "orderReference", header: "Order", render: (e) => (e.orderReference ? <span className="font-mono text-xs">{e.orderReference}</span> : dash) },
    { key: "bundle", header: "Bundle", render: (e) => <span className="uppercase">{e.bundle}</span> },
    { key: "type", header: "Type", render: (e) => <span className="font-medium">{e.type}</span> },
    { key: "amount", header: "Amount", align: "right", render: (e) => <Amount value={e.amount} signed /> },
    { key: "status", header: "Status", render: (e) => <StatusBadge status={e.status} /> },
];

export default function SubagentTransactions({ stats, transactions, filters }: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (patch: Record<string, string | null>) =>
        router.get(
            transactionsRoute.url(),
            {
                status: filters.status,
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
                    description="Your earnings from every storefront sale."
                    actions={<DateRangePicker value={{ range: filters.range, from: filters.from, to: filters.to }} onChange={applyRange} />}
                />

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

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border p-4">
                        <Select value={filters.status} onValueChange={(v) => apply({ status: v })}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_FILTERS.map((f) => (
                                    <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Search order or phone…"
                            className="ml-auto w-full bg-background sm:w-72"
                        />
                    </div>
                    <DataTable
                        columns={columns}
                        rows={transactions.data ?? []}
                        rowKey={(e) => e.id}
                        emptyMessage="No earnings yet. Sales through your storefront will show here."
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

SubagentTransactions.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Transactions", href: "/transactions" },
    ],
};
