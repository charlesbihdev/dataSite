import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { agent as agentOrders, bulk as bulkOrders, exportMethod as exportOrders, regular as regularOrders, verifyPayment } from '@/actions/App/Http/Controllers/Admin/OrdersController';
import { AdminOrder, OrderDetailDialog } from '@/components/admin/orders/order-detail-dialog';
import { SellerTypeBadge } from '@/components/admin/orders/seller-type-badge';
import { Column, DataTable } from '@/components/common/data-table';
import { DateRangePicker, DateRangeValue } from '@/components/common/date-range-picker';
import { PageHeader } from '@/components/common/page-header';
import { Pagination, PageLink } from '@/components/common/pagination';
import { StatTile } from '@/components/common/stat-tile';
import { StatusBadge } from '@/components/common/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cedis } from '@/lib/format';
import { cn } from '@/lib/utils';

export type OrderSegment = 'agent' | 'regular';

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
    seller: string;
    source: string;
    payment: string;
    q: string;
    range: string;
    from: string | null;
    to: string | null;
}

const STATUSES = ['all', 'pending', 'processing', 'completed', 'failed', 'refunded'];
const NETWORKS = ['all', 'mtn', 'telecel', 'at'];
const SELLERS = ['all', 'agent', 'subagent'];
const SOURCES = ['all', 'portal', 'api'];
const PAYMENTS = ['all', 'paid', 'awaiting', 'failed'];

const PaymentBadge = ({ status }: { status: string }) => {
    if (status === 'awaiting') {
        return <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">Awaiting</span>;
    }
    if (status === 'failed') {
        return <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300">Failed</span>;
    }
    return <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Paid</span>;
};

export function OrdersPage({
    segment,
    orders,
    stats,
    filters,
}: {
    segment: OrderSegment;
    orders: Paginator;
    stats: Stats;
    filters: Filters;
}) {
    const isRegular = segment === 'regular';
    const indexUrl = isRegular ? regularOrders.url() : agentOrders.url();

    const [selected, setSelected] = useState<AdminOrder | null>(null);
    const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
    const [search, setSearch] = useState(filters.q);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (patch: Partial<Filters>) => {
        setSelectedIds([]); // selection is per-view; drop it when the list changes
        router.get(indexUrl, { ...filters, ...patch }, { preserveState: true, replace: true, preserveScroll: true });
    };

    const [applyStatus, setApplyStatus] = useState('');

    // Every row is selectable; the server scopes each bulk action to the orders it can act on
    // (verify/delete → awaiting, sync → processing, retry → failed), so a mixed selection is safe.
    const runBulk = (action: 'verify' | 'mark-verified' | 'delete' | 'sync' | 'retry' | 'apply-status', status?: string) => {
        if (action === 'delete' && !window.confirm(`Delete ${selectedIds.length} selected order(s)? Only awaiting ones are removed. This can't be undone.`)) {
            return;
        }
        router.post(
            bulkOrders.url(),
            { action, ids: selectedIds, ...(status ? { status } : {}) },
            { preserveScroll: true, onSuccess: () => setSelectedIds([]) },
        );
    };

    useEffect(() => setSearch(filters.q), [filters.q]);

    const onSearchChange = (value: string) => {
        setSearch(value);
        if (debounce.current) {
            clearTimeout(debounce.current);
        }
        debounce.current = setTimeout(() => apply({ q: value }), 400);
    };

    const applyRange = (next: DateRangeValue) => apply({ range: next.range, from: next.from ?? null, to: next.to ?? null });

    const columns: Column<AdminOrder>[] = [
        { key: 'reference', header: 'Reference', render: (o) => <span className="font-mono text-xs">{o.reference}</span> },
        {
            key: 'seller',
            header: isRegular ? 'Shop' : 'Seller',
            render: (o) => (
                <span className="flex items-center gap-2">
                    <span>{o.seller}</span>
                    <SellerTypeBadge type={o.sellerType} />
                </span>
            ),
        },
        { key: 'network', header: 'Network', render: (o) => <span className="uppercase">{o.network}</span> },
        { key: 'package', header: 'Package', render: (o) => `${o.capacityGb}GB` },
        isRegular
            ? { key: 'payment', header: 'Payment', render: (o) => <PaymentBadge status={o.paymentStatus} /> }
            : {
                  key: 'source',
                  header: 'Source',
                  render: (o) =>
                      o.source === 'api' ? (
                          <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">API</span>
                      ) : (
                          <span className="text-xs text-muted-foreground">Portal</span>
                      ),
              },
        { key: 'receiver', header: 'Receiver', render: (o) => <span className="font-mono text-xs">{o.beneficiary}</span> },
        { key: 'amount', header: 'Amount', align: 'right', render: (o) => cedis(o.cascade.customerPrice) },
        { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
        { key: 'createdAt', header: 'When', render: (o) => <span className="text-muted-foreground">{o.createdAt ?? '—'}</span> },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (o) => (
                <div className="flex items-center justify-end gap-1">
                    {isRegular && o.paymentStatus === 'awaiting' ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            title="Verify payment & dispatch"
                            aria-label="Verify payment & dispatch"
                            className="text-amber-600 hover:text-amber-700 dark:text-amber-400"
                            onClick={() => router.post(verifyPayment(o.id).url, {}, { preserveScroll: true })}
                        >
                            <BadgeCheck className="size-4" />
                        </Button>
                    ) : null}
                    <Button variant="ghost" size="sm" onClick={() => setSelected(o)}>
                        View
                    </Button>
                </div>
            ),
        },
    ];

    const tabClass = (active: boolean) =>
        cn(
            'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
            active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
        );

    return (
        <>
            <Head title={isRegular ? 'Admin — Regular Orders' : 'Admin — Agent Orders'} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={isRegular ? 'Regular Orders' : 'Agent Orders'}
                    description={
                        isRegular
                            ? 'Public storefront orders — verify payment before the bundle is dispatched.'
                            : 'Seller-placed orders (portal & API), their frozen cascade, and upstream status.'
                    }
                    actions={
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-1 rounded-xl border border-border p-1">
                                <Link href={agentOrders.url()} className={tabClass(!isRegular)}>
                                    Agent
                                </Link>
                                <Link href={regularOrders.url()} className={tabClass(isRegular)}>
                                    Regular
                                </Link>
                            </div>
                            <DateRangePicker value={{ range: filters.range, from: filters.from, to: filters.to }} onChange={applyRange} />
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <StatTile label="Total" value={String(stats.total)} hint="Orders in range" />
                    {isRegular ? (
                        <StatTile label="Awaiting" value={String(stats.pending)} hint="Need payment verification" />
                    ) : (
                        <StatTile label="Pending" value={String(stats.pending)} hint="Not yet dispatched" />
                    )}
                    <StatTile label="Processing" value={String(stats.processing)} hint="Awaiting upstream" />
                    <StatTile label="Completed" value={String(stats.completed)} hint="Delivered" />
                    <StatTile label="Failed" value={String(stats.failed)} hint="Reversed & refunded" />
                    <StatTile label="Refunded" value={String(stats.refunded)} hint="Returned by admin" />
                    <StatTile label="Revenue" value={cedis(stats.revenue)} hint="Completed orders" />
                    <StatTile label="Today" value={`${stats.todayCount} · ${cedis(stats.todayRevenue)}`} hint="Orders · revenue today" />
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

                    {isRegular ? (
                        <Select value={filters.payment} onValueChange={(v) => apply({ payment: v })}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PAYMENTS.map((p) => (
                                    <SelectItem key={p} value={p} className="capitalize">
                                        {p === 'all' ? 'All payments' : p === 'awaiting' ? 'Awaiting payment' : p}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <>
                            <Select value={filters.seller} onValueChange={(v) => apply({ seller: v })}>
                                <SelectTrigger className="w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {SELLERS.map((s) => (
                                        <SelectItem key={s} value={s} className="capitalize">
                                            {s === 'all' ? 'All sellers' : `${s}s`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select value={filters.source} onValueChange={(v) => apply({ source: v })}>
                                <SelectTrigger className="w-32">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {SOURCES.map((s) => (
                                        <SelectItem key={s} value={s} className={s === 'api' ? 'uppercase' : 'capitalize'}>
                                            {s === 'all' ? 'All sources' : s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </>
                    )}

                    <Input
                        value={search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Reference, receiver phone, or upstream ref…"
                        className="ml-auto w-72"
                    />

                    {!isRegular ? (
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={
                                    exportOrders.url({
                                        query: Object.fromEntries(
                                            Object.entries({ ...filters, segment }).filter(
                                                ([, v]) => v !== null && v !== '' && v !== 'all',
                                            ),
                                        ),
                                    })
                                }
                            >
                                Export CSV
                            </a>
                        </Button>
                    ) : null}
                </div>

                {selectedIds.length > 0 ? (
                    <div className="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-muted/40 px-4 py-2">
                        <span className="text-sm font-medium">{selectedIds.length} selected</span>
                        <div className="ml-auto flex flex-wrap gap-2">
                            {isRegular ? (
                                <>
                                    <Button size="sm" onClick={() => runBulk('verify')}>
                                        Verify payment
                                    </Button>
                                    <Button size="sm" variant="secondary" onClick={() => runBulk('mark-verified')}>
                                        Mark verified
                                    </Button>
                                    <Button size="sm" variant="ghost" className="text-danger hover:text-danger" onClick={() => runBulk('delete')}>
                                        Delete
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <Button size="sm" onClick={() => runBulk('sync')}>
                                        Sync status
                                    </Button>
                                    <Button size="sm" variant="secondary" onClick={() => runBulk('retry')}>
                                        Retry dispatch
                                    </Button>
                                    <div className="flex items-center gap-1">
                                        <Select value={applyStatus} onValueChange={setApplyStatus}>
                                            <SelectTrigger className="h-8 w-36">
                                                <SelectValue placeholder="Set status…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {STATUSES.filter((s) => s !== 'all').map((s) => (
                                                    <SelectItem key={s} value={s} className="capitalize">
                                                        {s}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={!applyStatus}
                                            onClick={() => runBulk('apply-status', applyStatus)}
                                        >
                                            Apply
                                        </Button>
                                    </div>
                                </>
                            )}
                            <Button size="sm" variant="ghost" onClick={() => setSelectedIds([])}>
                                Clear
                            </Button>
                        </div>
                    </div>
                ) : null}

                <DataTable
                    columns={columns}
                    rows={orders.data}
                    rowKey={(o) => o.id}
                    emptyMessage="No orders match these filters."
                    selectedIds={selectedIds}
                    onSelectionChange={setSelectedIds}
                />
                <Pagination links={orders.links} />
            </div>

            <OrderDetailDialog order={selected} segment={segment} onClose={() => setSelected(null)} />
        </>
    );
}
