import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { splits as profitSplits } from '@/actions/App/Http/Controllers/Admin/ResellersController';
import { ResellersTabs } from '@/components/admin/resellers/resellers-tabs';
import { ownSaleColumns, splitColumns } from '@/components/admin/resellers/split-columns';
import { SplitFilters } from '@/components/admin/resellers/split-filters';
import type { AgentOption, Paginated, Split, SplitFilters as Filters } from '@/components/admin/resellers/types';
import { DataTable } from '@/components/common/data-table';
import { DateRangePicker, type DateRangeValue } from '@/components/common/date-range-picker';
import { PageHeader } from '@/components/common/page-header';
import { Pagination } from '@/components/common/pagination';
import { StatTile } from '@/components/common/stat-tile';
import { cedis } from '@/lib/format';

interface Stats {
    sales: number;
    customerPaid: number;
    sellerCost: number;
    subagentShare: number;
    agentShare: number;
    platformShare: number;
    dbhCost: number;
    profitCredited: number;
    profitPending: number;
    problems: number;
}

export default function ProfitSplits({
    splits,
    stats,
    filters,
    agents,
}: {
    splits: Paginated<Split>;
    stats: Stats;
    filters: Filters;
    agents: AgentOption[];
}) {
    const [search, setSearch] = useState(filters.q);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => setSearch(filters.q), [filters.q]);

    const apply = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        router.get(
            profitSplits.url(),
            { ...next, problems: next.problems ? 1 : null, profit_only: next.profit_only ? 1 : null },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const onSearchChange = (value: string) => {
        setSearch(value);
        if (debounce.current) {
            clearTimeout(debounce.current);
        }
        debounce.current = setTimeout(() => apply({ q: value }), 400);
    };

    const applyRange = (next: DateRangeValue) => apply({ range: next.range, from: next.from ?? null, to: next.to ?? null });
    const share = (value: number) => (stats.customerPaid > 0 ? `${((value / stats.customerPaid) * 100).toFixed(1)}% of customer paid` : '—');
    const own = filters.scope === 'own';
    const salesHint = `${stats.sales} sales (excl. failed/refunded)`;
    const problems = (
        <StatTile
            label="Problems"
            value={String(stats.problems)}
            hint={stats.problems === 0 ? 'Every split checks out' : 'Tick “Problems only” to review'}
        />
    );

    return (
        <>
            <Head title="Admin — Profit Splits" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Profit Splits"
                    description={
                        own
                            ? "Each agent's own sales: what the customer paid, what it cost them, and the profit it earned."
                            : "How each subagent sale's money was shared between the subagent, their agent, the platform and DBH."
                    }
                    actions={
                        <div className="flex items-center gap-3">
                            <ResellersTabs active="splits" />
                            <DateRangePicker value={{ range: filters.range, from: filters.from, to: filters.to }} onChange={applyRange} />
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatTile label="Customer Paid" value={cedis(stats.customerPaid)} hint={salesHint} />
                    {own ? (
                        <>
                            <StatTile label="Their Cost" value={cedis(stats.sellerCost)} hint={share(stats.sellerCost)} />
                            <StatTile label="Shop Profit" value={cedis(stats.profitCredited)} hint={`+${cedis(stats.profitPending)} pending`} />
                        </>
                    ) : (
                        <>
                            <StatTile label="Subagent Share" value={cedis(stats.subagentShare)} hint={`${cedis(stats.profitCredited)} credited`} />
                            <StatTile label="Agent Share" value={cedis(stats.agentShare)} hint={share(stats.agentShare)} />
                        </>
                    )}
                    <StatTile label="Platform Share" value={cedis(stats.platformShare)} hint={share(stats.platformShare)} />
                    <StatTile label="DBH Cost" value={cedis(stats.dbhCost)} hint={share(stats.dbhCost)} />
                    {problems}
                </div>

                <SplitFilters filters={filters} agents={agents} search={search} onSearchChange={onSearchChange} onApply={apply} />

                <DataTable
                    columns={own ? ownSaleColumns : splitColumns}
                    rows={splits.data}
                    rowKey={(s) => s.id}
                    emptyMessage={own ? 'No agent sales match these filters.' : 'No subagent sales match these filters.'}
                />
                <Pagination links={splits.links} />
            </div>
        </>
    );
}
