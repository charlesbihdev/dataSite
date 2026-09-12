import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { index as ordersIndex } from '@/actions/App/Http/Controllers/Admin/OrdersController';
import { AdminOrder, OrderDetailDialog } from '@/components/admin/orders/order-detail-dialog';
import { Column, DataTable } from '@/components/common/data-table';
import { DateRangePicker, DateRangeValue } from '@/components/common/date-range-picker';
import { PageHeader } from '@/components/common/page-header';
import { Pagination, PageLink } from '@/components/common/pagination';
import { StatTile } from '@/components/common/stat-tile';
import { StatusBadge } from '@/components/common/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cedis } from '@/lib/format';

interface Paginator {
    data: AdminOrder[];
    links: PageLink[];
}

interface Stats {
    total: number;
    pending: number;
    processing: number;
    completed: number;
    failed: number;
    refunded: number;
    revenue: number;
    successRate: number | null;
    todayCount: number;
    todayRevenue: number;
}

interface Filters {
    status: string;
    network: string;
    q: string;
    range: string;
    from: string | null;
    to: string | null;
}

const STATUSES = ['all', 'pending', 'processing', 'completed', 'failed', 'refunded'];
const NETWORKS = ['all', 'mtn', 'telecel', 'at'];

export default function AdminOrders({ orders, stats, filters }: { orders: Paginator; stats: Stats; filters: Filters }) {
    const [selected, setSelected] = useState<AdminOrder | null>(null);
    const [search, setSearch] = useState(filters.q);

    const apply = (patch: Partial<Filters>) => {
        router.get(ordersIndex.url(), { ...filters, ...patch }, { preserveState: true, replace: true, preserveScroll: true });
    };

    const applyRange = (next: DateRangeValue) => {
        apply({ range: next.range, from: next.from ?? null, to: next.to ?? null });
    };

    const columns: Column<AdminOrder>[] = [
        { key: 'reference', header: 'Reference', render: (o) => <span className="font-mono text-xs">{o.reference}</span> },
        { key: 'seller', header: 'Seller' },
        { key: 'bundle', header: 'Bundle', render: (o) => `${o.network} ${o.capacityGb}GB` },
        { key: 'customer', header: 'Customer', align: 'right', render: (o) => cedis(o.cascade.customerPrice) },
        { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
        { key: 'createdAt', header: 'When', render: (o) => <span className="text-muted-foreground">{o.createdAt ?? '—'}</span> },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (o) => (
                <Button variant="ghost" size="sm" onClick={() => setSelected(o)}>
                    View
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Admin — Orders" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Orders"
                    description="Every order, its frozen cascade, and upstream status."
                    actions={
                        <DateRangePicker
                            value={{ range: filters.range, from: filters.from, to: filters.to }}
                            onChange={applyRange}
                        />
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <StatTile label="Total" value={String(stats.total)} hint="Orders in range" />
                    <StatTile label="Pending" value={String(stats.pending)} hint="Not yet dispatched" />
                    <StatTile label="Processing" value={String(stats.processing)} hint="Awaiting upstream" />
                    <StatTile label="Completed" value={String(stats.completed)} hint="Delivered" />
                    <StatTile label="Failed" value={String(stats.failed)} hint="Reversed & refunded" />
                    <StatTile label="Refunded" value={String(stats.refunded)} hint="Returned by admin" />
                    <StatTile label="Revenue" value={cedis(stats.revenue)} hint="Completed orders" />
                    <StatTile
                        label="Success rate"
                        value={stats.successRate !== null ? `${stats.successRate}%` : '—'}
                        hint="Completed ÷ total"
                    />
                    <StatTile
                        label="Today"
                        value={`${stats.todayCount} · ${cedis(stats.todayRevenue)}`}
                        hint="Orders · revenue today"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Select value={filters.status} onValueChange={(v) => apply({ status: v })}>
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUSES.map((s) => (
                                <SelectItem key={s} value={s} className="capitalize">
                                    {s === 'all' ? 'All statuses' : s}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={filters.network} onValueChange={(v) => apply({ network: v })}>
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {NETWORKS.map((n) => (
                                <SelectItem key={n} value={n} className="uppercase">
                                    {n === 'all' ? 'All networks' : n}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <form
                        className="ml-auto flex items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q: search });
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Reference or phone…"
                            className="w-56"
                        />
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </form>
                </div>

                <DataTable
                    columns={columns}
                    rows={orders.data}
                    rowKey={(o) => o.id}
                    emptyMessage="No orders match these filters."
                />
                <Pagination links={orders.links} />
            </div>

            <OrderDetailDialog order={selected} onClose={() => setSelected(null)} />
        </>
    );
}
