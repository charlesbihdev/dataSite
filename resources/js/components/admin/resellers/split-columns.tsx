import { CircleAlert, CircleCheck } from 'lucide-react';
import type { Column } from '@/components/common/data-table';
import { StatusBadge } from '@/components/common/status-badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cedis } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Split } from './types';

function Share({ value }: { value: number | null }) {
    if (value === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return <span className={cn('tabular-nums', value < 0 && 'font-medium text-danger')}>{cedis(value)}</span>;
}

function Check({ issues }: { issues: string[] }) {
    if (issues.length === 0) {
        return (
            <span className="inline-flex items-center gap-1 text-success" aria-label="Split checks out">
                <CircleCheck className="size-4" />
            </span>
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button type="button" className="inline-flex items-center gap-1 text-danger" aria-label="Split has problems">
                    <CircleAlert className="size-4" />
                    <span className="text-xs font-medium">{issues.length}</span>
                </button>
            </TooltipTrigger>
            <TooltipContent side="left" className="max-w-xs">
                <ul className="space-y-1">
                    {issues.map((issue) => (
                        <li key={issue}>{issue}</li>
                    ))}
                </ul>
            </TooltipContent>
        </Tooltip>
    );
}

export const splitColumns: Column<Split>[] = [
    {
        key: 'reference',
        header: 'Order',
        render: (s) => (
            <div>
                <div className="font-mono text-xs">{s.reference}</div>
                <div className="text-xs text-muted-foreground">{s.date ?? '—'}</div>
            </div>
        ),
    },
    {
        key: 'chain',
        header: 'Subagent → Agent',
        render: (s) => (
            <div>
                <div className="font-medium">{s.subagent}</div>
                <div className="text-xs text-muted-foreground">under {s.agent}</div>
            </div>
        ),
    },
    { key: 'customerPaid', header: 'Customer paid', align: 'right', render: (s) => <span className="font-medium">{cedis(s.customerPaid)}</span> },
    { key: 'subagentShare', header: 'Subagent', align: 'right', render: (s) => <Share value={s.subagentShare} /> },
    { key: 'agentShare', header: 'Agent', align: 'right', render: (s) => <Share value={s.agentShare} /> },
    { key: 'platformShare', header: 'Platform', align: 'right', render: (s) => <Share value={s.platformShare} /> },
    {
        key: 'dbhCost',
        header: 'DBH',
        align: 'right',
        render: (s) => (
            <span title={s.dbhActual ? 'Actual supplier charge' : 'Expected cost — supplier charge not recorded yet'}>
                <Share value={s.dbhCost} />
                {s.dbhCost !== null && !s.dbhActual ? <span className="ml-1 text-xs text-muted-foreground">est.</span> : null}
            </span>
        ),
    },
    {
        key: 'settlement',
        header: 'Settlement',
        render: (s) => <StatusBadge status={s.settlement === 'none' ? 'unsettled' : s.settlement} />,
    },
    { key: 'check', header: 'Check', render: (s) => <Check issues={s.issues} /> },
];
