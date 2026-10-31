import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment, useState } from 'react';
import { splits as profitSplits } from '@/actions/App/Http/Controllers/Admin/ResellersController';
import { EmptyState } from '@/components/common/empty-state';
import { StatusBadge } from '@/components/common/status-badge';
import { cedis } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Money, ResellerAgent } from './types';

function Amount({ value, note }: { value: number; note?: string | null }) {
    return (
        <div className="tabular-nums">
            <div className="font-medium">{cedis(value)}</div>
            {note ? <div className="text-xs text-muted-foreground">{note}</div> : null}
        </div>
    );
}

function Sales({ count, revenue }: { count: number; revenue: number }) {
    return (
        <div className="tabular-nums">
            <div className="font-medium">{count}</div>
            <div className="text-xs text-muted-foreground">{cedis(revenue)}</div>
        </div>
    );
}

const pendingNote = (pending: number) => (pending > 0 ? `+${cedis(pending)} pending` : null);

function Person({ name, username, phone }: { name: string; username: string | null; phone: string }) {
    return (
        <div>
            <div className="font-medium">{name}</div>
            <div className="text-xs text-muted-foreground">{username ? `@${username} · ${phone}` : phone}</div>
        </div>
    );
}

/** Total earned, withdrawn and available — the same three cells on every person's row. */
function MoneyCells({ row, cell }: { row: Money; cell: string }) {
    return (
        <>
            <td className={cn(cell, 'text-right')}>
                <Amount value={row.totalEarned} />
            </td>
            <td className={cn(cell, 'text-right')}>
                <Amount value={row.withdrawn} note={row.requested > 0 ? `+${cedis(row.requested)} requested` : null} />
            </td>
            <td className={cn(cell, 'text-right font-semibold tabular-nums', row.available < 0 && 'text-danger')}>{cedis(row.available)}</td>
        </>
    );
}

const HEADERS = ['Agent / Subagent', 'Subagents', 'Delivered sales', 'Shop profit', 'Commission', 'Total earned', 'Withdrawn', 'Available', 'Status', ''];

/**
 * Every agent with their subagents nested underneath (click a row to expand). Each row shows that
 * person's own money; the "All subagents" line keeps the group totals apart from the agent's. Mirrors
 * DataTable's look; it exists separately only because DataTable has no expandable rows.
 */
export function ResellerTree({ agents }: { agents: ResellerAgent[] }) {
    const [open, setOpen] = useState<Set<number>>(new Set());

    const toggle = (id: number) =>
        setOpen((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });

    if (agents.length === 0) {
        return (
            <div className="rounded-xl border border-border">
                <EmptyState message="No agents found." />
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            {HEADERS.map((h, i) => (
                                <th key={h || i} className={cn('whitespace-nowrap px-4 py-3 font-semibold', i >= 1 && i <= 7 && 'text-right')}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {agents.map((agent) => {
                            const hasSubagents = agent.subagentCount > 0;
                            const expanded = hasSubagents && open.has(agent.id);

                            return (
                                <Fragment key={agent.id}>
                                    <tr
                                        onClick={() => hasSubagents && toggle(agent.id)}
                                        className={cn('border-b border-border/60 transition-colors', hasSubagents && 'cursor-pointer hover:bg-muted/40')}
                                    >
                                        <td className="whitespace-nowrap px-4 py-4">
                                            <div className="flex items-center gap-2">
                                                <ChevronRight
                                                    className={cn(
                                                        'size-4 text-muted-foreground transition-transform',
                                                        expanded && 'rotate-90',
                                                        !hasSubagents && 'invisible',
                                                    )}
                                                />
                                                <Person name={agent.name} username={agent.username} phone={agent.phone} />
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-right font-medium tabular-nums">{agent.subagentCount}</td>
                                        <td className="px-4 py-4 text-right">
                                            <Sales count={agent.ownSales} revenue={agent.ownRevenue} />
                                        </td>
                                        <td className="px-4 py-4 text-right">
                                            <Amount value={agent.ownProfit} note={pendingNote(agent.ownPending)} />
                                        </td>
                                        <td className="px-4 py-4 text-right">
                                            <Amount value={agent.agentEarned} note={pendingNote(agent.agentPending)} />
                                        </td>
                                        <MoneyCells row={agent} cell="px-4 py-4" />
                                        <td className="px-4 py-4">
                                            <StatusBadge status={agent.isActive ? 'active' : 'suspended'} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-4 text-right">
                                            <Link
                                                href={profitSplits.url({ query: { scope: 'own', agent: agent.id } })}
                                                onClick={(e) => e.stopPropagation()}
                                                className="text-sm font-medium text-brand hover:underline"
                                            >
                                                View sales
                                            </Link>
                                        </td>
                                    </tr>
                                    {expanded ? (
                                        <tr className="border-b border-border bg-muted/40">
                                            <td className="whitespace-nowrap py-3 pl-14 pr-4 font-medium">
                                                All {agent.subagentCount} subagents
                                                <div className="text-xs font-normal text-muted-foreground">Group total, not the agent's money</div>
                                            </td>
                                            <td />
                                            <td className="px-4 py-3 text-right">
                                                <Sales count={agent.sales} revenue={agent.revenue} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Amount value={agent.subagentEarned} note={pendingNote(agent.subagentPending)} />
                                            </td>
                                            <td colSpan={6} />
                                        </tr>
                                    ) : null}
                                    {expanded
                                        ? agent.subagents.map((sub) => (
                                              <tr key={`s${sub.id}`} className="border-b border-border/60 bg-muted/20">
                                                  <td className="whitespace-nowrap py-3 pl-14 pr-4">
                                                      <Person name={sub.name} username={sub.username} phone={sub.phone} />
                                                  </td>
                                                  <td className="px-4 py-3 text-right text-muted-foreground">—</td>
                                                  <td className="px-4 py-3 text-right">
                                                      <Sales count={sub.sales} revenue={sub.revenue} />
                                                  </td>
                                                  <td className="px-4 py-3 text-right">
                                                      <Amount value={sub.subagentEarned} note={pendingNote(sub.subagentPending)} />
                                                  </td>
                                                  <td className="px-4 py-3 text-right text-muted-foreground">
                                                      <Amount value={sub.agentEarned} note="to agent" />
                                                  </td>
                                                  <MoneyCells row={sub} cell="px-4 py-3" />
                                                  <td className="px-4 py-3">
                                                      <StatusBadge status={sub.isActive ? 'active' : 'suspended'} />
                                                  </td>
                                                  <td className="whitespace-nowrap px-4 py-3 text-right">
                                                      <Link
                                                          href={profitSplits.url({ query: { agent: agent.id, subagent: sub.id } })}
                                                          className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                                                      >
                                                          View sales
                                                      </Link>
                                                  </td>
                                              </tr>
                                          ))
                                        : null}
                                </Fragment>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
