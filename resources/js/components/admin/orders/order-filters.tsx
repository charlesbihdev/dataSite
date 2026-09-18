import { exportMethod as exportOrders } from '@/actions/App/Http/Controllers/Admin/OrdersController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Filters, NETWORKS, type OrderSegment, PAYMENTS, SELLERS, SOURCES, STATUSES } from './orders-shared';

/**
 * Filter toolbar for the admin order pages. Regular (storefront) shows the payment filter; the
 * Agent page shows seller + source filters and the CSV export.
 */
export function OrderFilters({
    filters,
    isRegular,
    segment,
    search,
    onSearchChange,
    onApply,
}: {
    filters: Filters;
    isRegular: boolean;
    segment: OrderSegment;
    search: string;
    onSearchChange: (value: string) => void;
    onApply: (patch: Partial<Filters>) => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <Select value={filters.status} onValueChange={(v) => onApply({ status: v })}>
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

            <Select value={filters.network} onValueChange={(v) => onApply({ network: v })}>
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
                <Select value={filters.payment} onValueChange={(v) => onApply({ payment: v })}>
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
                    <Select value={filters.seller} onValueChange={(v) => onApply({ seller: v })}>
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

                    <Select value={filters.source} onValueChange={(v) => onApply({ source: v })}>
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
                        href={exportOrders.url({
                            query: Object.fromEntries(
                                Object.entries({ ...filters, segment }).filter(([, v]) => v !== null && v !== '' && v !== 'all'),
                            ),
                        })}
                    >
                        Export CSV
                    </a>
                </Button>
            ) : null}
        </div>
    );
}
