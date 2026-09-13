import { Head, router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Amount } from "@/components/common/amount";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cedis } from "@/lib/format";
import { subagentSales } from "@/routes/agent";

interface Sale {
    id: number;
    reference: string;
    subagent: string;
    customer: string;
    network: string;
    capacityGb: number;
    amount: number;
    margin: number;
    status: string;
    date: string | null;
}

interface Props {
    orders: { data: Sale[]; links: PageLink[] };
    subagents: { id: number; name: string }[];
    filters: { subagent: string; status: string; network: string; q: string };
    stats: { total: number; delivered: number; placed: number; margin30d: number };
}

const STATUSES = ["all", "pending", "processing", "completed", "failed", "refunded"];
const NETWORKS = ["all", "mtn", "telecel", "at"];

const columns: Column<Sale>[] = [
    { key: "reference", header: "Order code", render: (s) => <span className="font-mono text-xs">{s.reference}</span> },
    { key: "subagent", header: "Sub-agent", render: (s) => <span className="font-medium">{s.subagent}</span> },
    { key: "customer", header: "Customer", render: (s) => <span className="font-mono text-xs">{s.customer}</span> },
    { key: "network", header: "Package", render: (s) => <span className="uppercase">{s.network} {s.capacityGb}GB</span> },
    { key: "amount", header: "Amount", align: "right", render: (s) => cedis(s.amount) },
    { key: "margin", header: "Your margin", align: "right", render: (s) => <Amount value={s.margin} /> },
    { key: "status", header: "Status", render: (s) => <StatusBadge status={s.status} /> },
    { key: "date", header: "Date", render: (s) => <span className="text-muted-foreground">{s.date ?? "—"}</span> },
];

export default function SubagentSales({ orders, subagents, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (patch: Record<string, string>) =>
        router.get(
            subagentSales.url(),
            { subagent: filters.subagent, status: filters.status, network: filters.network, q: search, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );

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
            <Head title="Sub-agent sales" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="Sub-agent sales" description="Orders placed through your sub-agents' storefronts. You earn your margin on each one." />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile label="Total sales" value={String(stats.total)} hint="Across all your sub-agents" />
                    <StatTile label="Delivered" value={String(stats.delivered)} hint="Successfully fulfilled" />
                    <StatTile label="Placed (in progress)" value={String(stats.placed)} hint="Awaiting delivery" />
                    <StatTile label="Your margin (30d)" value={cedis(stats.margin30d)} hint="Earned from these sales" />
                </div>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border p-4">
                        <Select value={filters.subagent} onValueChange={(v) => apply({ subagent: v })}>
                            <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sub-agents</SelectItem>
                                {subagents.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={filters.status} onValueChange={(v) => apply({ status: v })}>
                            <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s} value={s} className="capitalize">{s === "all" ? "All statuses" : s}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={filters.network} onValueChange={(v) => apply({ network: v })}>
                            <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {NETWORKS.map((n) => (
                                    <SelectItem key={n} value={n} className="uppercase">{n === "all" ? "All networks" : n}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Order code, phone, customer…"
                            className="ml-auto w-full bg-background sm:w-72"
                        />
                    </div>
                    <DataTable columns={columns} rows={orders.data} rowKey={(s) => s.id} emptyMessage="No sub-agent sales yet." />
                    {orders.links.length > 3 && (
                        <div className="border-t border-border p-4">
                            <Pagination links={orders.links} />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

SubagentSales.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Sub-agent sales", href: "/subagent-sales" },
    ],
};
