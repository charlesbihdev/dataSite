import { Head } from '@inertiajs/react';
import { Column, DataTable } from '@/components/common/data-table';
import { PageHeader } from '@/components/common/page-header';
import { StatTile } from '@/components/common/stat-tile';
import { StatusBadge } from '@/components/common/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cedis } from '@/lib/format';

interface Stats {
    revenue: number;
    supplierCost: number;
    grossProfit: number;
    platformProfit: number;
    resellerEarnings: number;
    orders: { total: number; completed: number; processing: number; failed: number };
    agents: number;
    subagents: number;
}

interface NetworkRow {
    network: string;
    orders: number;
    revenue: number;
    profit: number;
}

interface RecentOrder {
    id: number;
    reference: string;
    seller: string;
    network: string;
    capacityGb: number;
    customerPrice: number;
    baseCost: number | null;
    status: string;
    createdAt: string | null;
}

const orderColumns: Column<RecentOrder>[] = [
    { key: 'reference', header: 'Reference', render: (o) => <span className="font-mono text-xs">{o.reference}</span> },
    { key: 'seller', header: 'Seller' },
    { key: 'bundle', header: 'Bundle', render: (o) => `${o.network} ${o.capacityGb}GB` },
    { key: 'customerPrice', header: 'Customer', align: 'right', render: (o) => cedis(o.customerPrice) },
    { key: 'baseCost', header: 'Base cost', align: 'right', render: (o) => (o.baseCost !== null ? cedis(o.baseCost) : '—') },
    { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
    { key: 'createdAt', header: 'When', render: (o) => <span className="text-muted-foreground">{o.createdAt ?? '—'}</span> },
];

export default function AdminDashboard({
    stats,
    byNetwork,
    recentOrders,
}: {
    stats: Stats;
    byNetwork: NetworkRow[];
    recentOrders: RecentOrder[];
}) {
    return (
        <>
            <Head title="Admin — Overview" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Overview"
                    description="Money and fulfillment across all agents. Figures cover completed orders."
                />

                {/* Money reads as a chain: Revenue − Supplier cost = Gross profit, of which the
                    platform keeps its share and agents/subagents keep the rest. */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile label="Revenue" value={cedis(stats.revenue)} hint="What customers paid" />
                    <StatTile label="Supplier cost" value={cedis(stats.supplierCost)} hint="Paid to Databundleshub" />
                    <StatTile label="Gross profit" value={cedis(stats.grossProfit)} hint="Revenue − supplier cost" />
                    <StatTile
                        label="Platform profit"
                        value={cedis(stats.platformProfit)}
                        hint={`Our share · ${cedis(stats.resellerEarnings)} goes to agents & subagents`}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatTile
                        label="Orders"
                        value={String(stats.orders.completed + stats.orders.processing)}
                        hint={
                            <>
                                {stats.orders.completed} delivered · {stats.orders.processing} processing ·{' '}
                                <span className={stats.orders.failed > 0 ? 'text-danger' : undefined}>
                                    {stats.orders.failed} failed
                                </span>
                            </>
                        }
                    />
                    <StatTile label="Agents" value={String(stats.agents)} hint="Registered agents" />
                    <StatTile label="Subagents" value={String(stats.subagents)} hint="Across all agents" />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Revenue by network</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {byNetwork.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No completed orders yet.</p>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-3">
                                {byNetwork.map((row) => (
                                    <div key={row.network} className="rounded-lg border border-border p-4">
                                        <div className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            {row.network}
                                        </div>
                                        <div className="mt-1 text-lg font-bold tabular-nums">{cedis(row.revenue)}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.orders} orders · {cedis(row.profit)} profit
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    <h2 className="text-base font-semibold">Recent orders</h2>
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
