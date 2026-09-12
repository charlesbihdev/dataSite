import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { Button } from "@/components/ui/button";
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

interface Props {
    orders: { data: Order[]; links: PageLink[] };
    filters: { q?: string; date_from?: string; date_to?: string };
    stats: Stats;
}

export default function AgentOrders({ orders, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.q || "");
    const [dateFrom, setDateFrom] = useState(filters.date_from || "");
    const [dateTo, setDateTo] = useState(filters.date_to || "");

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

    const applyFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/orders',
            {
                q: search,
                date_from: dateFrom,
                date_to: dateTo,
            },
            { preserveState: true, replace: true }
        );
    };

    const clearFilter = () => {
        setSearch("");
        setDateFrom("");
        setDateTo("");
        router.get('/orders', {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Store Orders" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="Store Orders" description="Track purchases and view your sales value over time." />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatTile
                        label="Total Revenue"
                        value={cedis(stats.sales)}
                        hint={dateFrom || dateTo ? "For selected period" : "All time"}
                    />
                    <StatTile
                        label="Net Profit"
                        value={cedis(stats.profit)}
                        hint={dateFrom || dateTo ? "For selected period" : "All time"}
                    />
                    <StatTile
                        label="Orders Count"
                        value={String(stats.count)}
                        hint={dateFrom || dateTo ? "For selected period" : "All time"}
                    />
                </div>

                <form
                    className="flex flex-wrap items-center gap-2 bg-background/50 backdrop-blur-xl border border-border/50 p-2 rounded-lg"
                    onSubmit={applyFilter}
                >
                    <Input
                        placeholder="Search ref or phone..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-48 bg-background"
                    />
                    <div className="flex items-center gap-1">
                        <label className="text-xs text-muted-foreground ml-1">From:</label>
                        <Input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="w-auto bg-background"
                        />
                    </div>
                    <div className="flex items-center gap-1">
                        <label className="text-xs text-muted-foreground ml-1">To:</label>
                        <Input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="w-auto bg-background"
                        />
                    </div>
                    <Button type="submit" variant="default">
                        Filter
                    </Button>
                    {(filters.q || filters.date_from || filters.date_to) && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={clearFilter}
                        >
                            Clear
                        </Button>
                    )}
                </form>

                <div className="flex-1 rounded-xl border border-zinc-200/50 bg-white/50 shadow-sm backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                    <DataTable
                        columns={columns}
                        rows={orders.data || []}
                        rowKey={(o) => o.id}
                        emptyMessage="No orders found."
                    />
                    {orders.links.length > 3 && (
                        <div className="border-t border-zinc-200/50 p-4 dark:border-zinc-800">
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
