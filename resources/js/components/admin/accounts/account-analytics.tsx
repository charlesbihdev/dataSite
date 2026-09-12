import { ChevronDown } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cedis } from '@/lib/format';
import type { AccountAnalytics, AccountType } from './types';

// Compact insights for the active tab, each in its own card (matching the analytics dashboards):
// performance averages, wallet-balance spread, and the busiest accounts. Collapsed by default —
// revealed on demand so the accounts table stays above the fold.
export function AccountAnalytics({ type, analytics }: { type: AccountType; analytics: AccountAnalytics }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-col gap-4">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                className="flex w-fit items-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ChevronDown className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                {open ? 'Hide insights' : 'Show insights'}
            </button>

            {open ? <InsightGrid type={type} analytics={analytics} /> : null}
        </div>
    );
}

function InsightGrid({ type, analytics }: { type: AccountType; analytics: AccountAnalytics }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <InsightCard title="Performance">
                <Metric label="Avg orders / account" value={String(analytics.avgOrders)} />
                <Metric label="Avg order value" value={cedis(analytics.avgRevenue)} />
                <Metric label="Active rate" value={`${analytics.activeRate}%`} />
            </InsightCard>

            <InsightCard title="Wallet balances">
                <Metric label="Positive" value={String(analytics.balance.positive)} tone="text-success" />
                <Metric label="Zero" value={String(analytics.balance.zero)} tone="text-warning-foreground" />
                <Metric label="Negative" value={String(analytics.balance.negative)} tone="text-danger" />
            </InsightCard>

            <InsightCard title={`Busiest ${type}`}>
                {analytics.topByOrders.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No orders yet.</p>
                ) : (
                    analytics.topByOrders.map((row) => (
                        <Metric key={row.name} label={row.name} value={`${row.ordersCount} · ${cedis(row.ordersTotal)}`} />
                    ))
                )}
            </InsightCard>
        </div>
    );
}

function InsightCard({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">{children}</CardContent>
        </Card>
    );
}

function Metric({ label, value, tone }: { label: string; value: string; tone?: string }) {
    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <span className="truncate text-muted-foreground">{label}</span>
            <span className={`font-semibold tabular-nums ${tone ?? ''}`}>{value}</span>
        </div>
    );
}
