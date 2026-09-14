import { Head } from "@inertiajs/react";
import { ArrowLeftRight, ShoppingBag, TrendingUp, Wallet } from "lucide-react";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { cedis } from "@/lib/format";

interface Stats {
    walletBalance: number;
    ordersCount: number;
    salesRevenue: number;
    earnings: number;
}

interface RecentOrder {
    id: number;
    reference: string;
    network: string;
    capacityGb: number;
    customerPrice: number;
    status: string;
    createdAt: string | null;
}

const orderColumns: Column<RecentOrder>[] = [
    { key: "reference", header: "Reference", render: (o) => <span className="font-mono text-xs">{o.reference}</span> },
    { key: "bundle", header: "Bundle", render: (o) => <span className="uppercase">{o.network} {o.capacityGb}GB</span> },
    { key: "customerPrice", header: "Amount", align: "right", render: (o) => cedis(o.customerPrice) },
    { key: "status", header: "Status", render: (o) => <StatusBadge status={o.status} /> },
    { key: "createdAt", header: "When", render: (o) => <span className="text-muted-foreground">{o.createdAt ?? "—"}</span> },
];

export default function SubagentDashboard({ stats, recentOrders }: { stats: Stats; recentOrders: RecentOrder[] }) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="Dashboard" description="Your sales, wallet, and recent orders." />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile label="Wallet Balance" value={cedis(stats.walletBalance)} hint={<span className="inline-flex items-center gap-1"><Wallet className="size-3" /> Available</span>} />
                    <StatTile label="Total Sales" value={cedis(stats.salesRevenue)} hint={<span className="inline-flex items-center gap-1"><TrendingUp className="size-3" /> Paid orders</span>} />
                    <StatTile label="Earnings" value={cedis(stats.earnings)} hint={<span className="inline-flex items-center gap-1"><ArrowLeftRight className="size-3" /> Matured commission</span>} />
                    <StatTile label="Orders" value={String(stats.ordersCount)} hint={<span className="inline-flex items-center gap-1"><ShoppingBag className="size-3" /> All time</span>} />
                </div>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="border-b border-border p-4">
                        <h2 className="text-sm font-semibold">Recent orders</h2>
                    </div>
                    <DataTable
                        columns={orderColumns}
                        rows={recentOrders}
                        rowKey={(o) => o.id}
                        emptyMessage="No orders yet."
                    />
                </div>
            </div>
        </>
    );
}

SubagentDashboard.layout = {
    breadcrumbs: [{ title: "Dashboard", href: "/dashboard" }],
};
