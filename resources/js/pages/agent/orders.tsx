import { Head, router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Column, DataTable } from "@/components/common/data-table";
import { DateRangePicker, DateRangeValue } from "@/components/common/date-range-picker";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { cedis } from "@/lib/format";

interface Order {
    id: number;
    reference: string;
    created_at: string;
    network: string;
    capacity_gb: string;
    beneficiary_phone: string;
    customer_price: string;
    status: string;
}

interface Stats {
    count: number;
    sales: number;
    profit: number;
}

interface Filters {
    q?: string;
    range?: string;
    from?: string | null;
    to?: string | null;
}

interface Props {
    orders: { data: Order[]; links: PageLink[] };
    filters: Filters;
    stats: Stats;
}

export default function AgentOrders({ orders, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    const range = filters.range ?? "all";
    const inRange = range !== "all";

    // Both the search box and the DateRangePicker reload this same route carrying
    // the other's current value, so applying one never drops the other. The stats
    // are computed from the same filtered query server-side, so the cards always
    // reflect exactly what the table shows.
    const apply = (patch: Record<string, string | null>) =>
        router.get(
            "/orders",
            { q: search, range, from: filters.from ?? null, to: filters.to ?? null, ...patch },
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

    const applyRange = (next: DateRangeValue) =>
        apply({ range: next.range, from: next.from ?? null, to: next.to ?? null });

    const columns: Column<Order>[] = [
        {
            key: "reference",
            header: "Reference",
            render: (o) => <span className="font-mono text-xs">{o.reference}</span>,
        },
        {
            key: "created_at",
            header: "Date",
            render: (o) => new Date(o.created_at).toLocaleString(),
        },
        {
            key: "network",
            header: "Plan",
            render: (o) => <span className="capitalize">{o.network} {o.capacity_gb}GB</span>,
        },
        { key: "beneficiary_phone", header: "Beneficiary" },
        {
            key: "customer_price",
            header: "Amount",
            render: (o) => cedis(o.customer_price),
        },
        {
            key: "status",
            header: "Status",
            render: (o) => (
                <Badge variant={o.status === "completed" ? "default" : o.status === "failed" ? "destructive" : "secondary"}>
                    {o.status}
                </Badge>
            ),
        },
    ];

    return (
        <>
            <Head title="Store Orders" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="Store Orders"
                    description="Track purchases and view your sales value over time."
                    actions={<DateRangePicker value={{ range, from: filters.from, to: filters.to }} onChange={applyRange} />}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatTile label="Total Revenue" value={cedis(stats.sales)} hint={inRange ? "For selected period" : "All time"} />
                    <StatTile label="Net Profit" value={cedis(stats.profit)} hint={inRange ? "For selected period" : "All time"} />
                    <StatTile label="Orders Count" value={String(stats.count)} hint={inRange ? "For selected period" : "All time"} />
                </div>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    {/* Search bar — scopes the table below only. */}
                    <div className="border-b border-border p-4">
                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Search by reference, phone, network, status, or upstream ref…"
                            className="w-full bg-background sm:w-96"
                        />
                    </div>
                    <DataTable
                        columns={columns}
                        rows={orders.data || []}
                        rowKey={(o) => o.id}
                        emptyMessage="No orders found."
                    />
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

AgentOrders.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }, { title: 'Orders', href: '/orders' }],
};
