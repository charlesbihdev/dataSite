import { Head, router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Order, OrderDetailDialog } from "@/components/agent/order-detail-dialog";
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
import { orders as ordersRoute } from "@/routes/subagent";

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
    status?: string;
    network?: string;
}

interface Props {
    orders: { data: Order[]; links: PageLink[] };
    filters: Filters;
    stats: Stats;
}

const STATUSES = ["all", "pending", "processing", "completed", "failed", "refunded"];
const NETWORKS = ["all", "mtn", "telecel", "at"];

export default function SubagentOrders({ orders, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const [selected, setSelected] = useState<Order | null>(null);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    const range = filters.range ?? "all";
    const inRange = range !== "all";
    const status = filters.status ?? "all";
    const network = filters.network ?? "all";

    const apply = (patch: Record<string, string | null>) =>
        router.get(
            ordersRoute.url(),
            { q: search, range, from: filters.from ?? null, to: filters.to ?? null, status, network, ...patch },
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
        { key: "reference", header: "Reference", render: (o) => <span className="font-mono text-xs">{o.reference}</span> },
        { key: "created_at", header: "Date", render: (o) => <span className="text-muted-foreground">{new Date(o.created_at).toLocaleString()}</span> },
        { key: "network", header: "Network", render: (o) => <span className="uppercase">{o.network}</span> },
        { key: "package", header: "Package", render: (o) => `${o.capacity_gb}GB` },
        { key: "beneficiary_phone", header: "Beneficiary", render: (o) => <span className="font-mono text-xs">{o.beneficiary_phone}</span> },
        { key: "customer_price", header: "Amount", align: "right", render: (o) => cedis(o.customer_price) },
        { key: "payment_status", header: "Payment", render: (o) => <StatusBadge status={o.payment_status} /> },
        { key: "status", header: "Delivery", render: (o) => <StatusBadge status={o.status} /> },
        {
            key: "action",
            header: "",
            align: "right",
            render: (o) => (
                <div className="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="sm" onClick={() => setSelected(o)}>
                        View
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Orders" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="Orders"
                    description="Your storefront sales and their delivery status."
                    actions={<DateRangePicker value={{ range, from: filters.from, to: filters.to }} onChange={applyRange} />}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatTile label="Paid revenue" value={cedis(stats.sales)} hint={inRange ? "For selected period" : "All time"} />
                    <StatTile label="Net Profit" value={cedis(stats.profit)} hint={inRange ? "For selected period" : "All time"} />
                    <StatTile label="Orders Count" value={String(stats.count)} hint={inRange ? "For selected period" : "All time"} />
                </div>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border p-4">
                        <Select value={status} onValueChange={(v) => apply({ status: v })}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s} value={s} className="capitalize">
                                        {s === "all" ? "All statuses" : s}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={network} onValueChange={(v) => apply({ network: v })}>
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {NETWORKS.map((n) => (
                                    <SelectItem key={n} value={n} className="uppercase">
                                        {n === "all" ? "All networks" : n}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Search reference, phone, or upstream ref…"
                            className="ml-auto w-full bg-background sm:w-80"
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

            <OrderDetailDialog order={selected} onClose={() => setSelected(null)} />
        </>
    );
}

SubagentOrders.layout = {
    breadcrumbs: [{ title: "Dashboard", href: "/dashboard" }, { title: "Orders", href: "/orders" }],
};
