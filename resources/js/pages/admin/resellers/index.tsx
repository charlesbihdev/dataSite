import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { index as resellersIndex } from '@/actions/App/Http/Controllers/Admin/ResellersController';
import { ResellerTree } from '@/components/admin/resellers/reseller-tree';
import { ResellersTabs } from '@/components/admin/resellers/resellers-tabs';
import type { Paginated, ResellerAgent } from '@/components/admin/resellers/types';
import { DateRangePicker, type DateRangeValue } from '@/components/common/date-range-picker';
import { PageHeader } from '@/components/common/page-header';
import { Pagination } from '@/components/common/pagination';
import { StatTile } from '@/components/common/stat-tile';
import { Input } from '@/components/ui/input';
import { cedis } from '@/lib/format';

interface Filters {
    q: string;
    range: string;
    from: string | null;
    to: string | null;
}

interface Stats {
    agents: number;
    subagents: number;
    agentShopProfit: number;
    agentEarned: number;
    subagentEarned: number;
    pending: number;
}

export default function Resellers({ agents, stats, filters }: { agents: Paginated<ResellerAgent>; stats: Stats; filters: Filters }) {
    const [search, setSearch] = useState(filters.q);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => setSearch(filters.q), [filters.q]);

    const apply = (patch: Partial<Filters>) =>
        router.get(resellersIndex.url(), { ...filters, ...patch }, { preserveState: true, replace: true, preserveScroll: true });

    const onSearchChange = (value: string) => {
        setSearch(value);
        if (debounce.current) {
            clearTimeout(debounce.current);
        }
        debounce.current = setTimeout(() => apply({ q: value }), 400);
    };

    const applyRange = (next: DateRangeValue) => apply({ range: next.range, from: next.from ?? null, to: next.to ?? null });

    return (
        <>
            <Head title="Admin — Resellers" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Resellers"
                    description="Every agent and their subagents: what each person earned, withdrew and has available."
                    actions={
                        <div className="flex items-center gap-3">
                            <ResellersTabs active="tree" />
                            <DateRangePicker value={{ range: filters.range, from: filters.from, to: filters.to }} onChange={applyRange} />
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatTile label="Agents" value={String(stats.agents)} hint="All registered agents" />
                    <StatTile label="Subagents" value={String(stats.subagents)} hint="Registered under agents" />
                    <StatTile label="Agent Shop Profit" value={cedis(stats.agentShopProfit)} hint="Credited from their own sales" />
                    <StatTile label="Agent Commission" value={cedis(stats.agentEarned)} hint="Credited from subagent sales" />
                    <StatTile label="Subagent Profit" value={cedis(stats.subagentEarned)} hint={`${cedis(stats.pending)} still pending`} />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-muted-foreground">The date filter changes sales and earnings. Withdrawn and Available are always all-time.</p>
                    <Input
                        value={search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Agent or subagent name, username, phone…"
                        className="w-80"
                    />
                </div>

                <ResellerTree agents={agents.data} />
                <Pagination links={agents.links} />
            </div>
        </>
    );
}
