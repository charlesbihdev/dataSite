import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment, useState } from 'react';
import { splits as profitSplits } from '@/actions/App/Http/Controllers/Admin/ResellersController';
import { EmptyState } from '@/components/common/empty-state';
import { StatusBadge } from '@/components/common/status-badge';
import { cedis } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Figures, ResellerAgent } from './types';

function Earned({ value, pending }: { value: number; pending: number }) {
    return (
        <div className="tabular-nums">
            <div className="font-medium">{cedis(value)}</div>
            {pending > 0 ? <div className="text-xs text-muted-foreground">+{cedis(pending)} pending</div> : null}
        </div>
    );
}

function Person({ name, username, phone }: { name: string; username: string | null; phone: string }) {
    return (
        <div>
            <div className="font-medium">{name}</div>
            <div className="text-xs text-muted-foreground">{username ? `@${username} · ${phone}` : phone}</div>
        </div>
    );
}

function FigureCells({ row }: { row: Figures }) {
    return (
        <>
            <td className="px-4 py-4 text-right tabular-nums">
                <div className="font-medium">{row.sales}</div>
                <div className="text-xs text-muted-foreground">{cedis(row.revenue)}</div>
            </td>
            <td className="px-4 py-4 text-right">
                <Earned value={row.subagentEarned} pending={row.subagentPending} />
            </td>
            <td className="px-4 py-4 text-right">
                <Earned value={row.agentEarned} pending={row.agentPending} />
            </td>
        </>
    );
}

const HEADERS = ['Agent / Subagent', 'Subagents', 'Delivered sales', 'Subagent profit', 'Agent commission', 'Status', ''];

/**
 * Agents with their subagents nested underneath (click a row to expand). Mirrors DataTable's
 * look; it exists separately only because DataTable has no expandable rows.
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
                <EmptyState message="No agents with subagents yet." />
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
                                <th key={h || i} className={cn('whitespace-nowrap px-4 py-3 font-semibold', i >= 1 && i <= 4 && 'text-right')}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {agents.map((agent) => {
                            const expanded = open.has(agent.id);

                            return (
                                <Fragment key={agent.id}>
                                    <tr
                                        onClick={() => toggle(agent.id)}
                                        className="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40"
                                    >
                                        <td className="whitespace-nowrap px-4 py-4">
                                            <div className="flex items-center gap-2">
                                                <ChevronRight
                                                    className={cn('size-4 text-muted-foreground transition-transform', expanded && 'rotate-90')}
                                                />
                                                <Person name={agent.name} username={agent.username} phone={agent.phone} />
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-right font-medium tabular-nums">{agent.subagentCount}</td>
                                        <FigureCells row={agent} />
                                        <td className="px-4 py-4">
                                            <StatusBadge status={agent.isActive ? 'active' : 'suspended'} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-4 text-right">
                                            <Link
                                                href={profitSplits.url({ query: { agent: agent.id } })}
                                                onClick={(e) => e.stopPropagation()}
                                                className="text-sm font-medium text-brand hover:underline"
                                            >
                                                View splits
                                            </Link>
                                        </td>
                                    </tr>
                                    {expanded
                                        ? agent.subagents.map((sub) => (
                                              <tr key={`s${sub.id}`} className="border-b border-border/60 bg-muted/20">
                                                  <td className="whitespace-nowrap py-3 pl-14 pr-4">
                                                      <Person name={sub.name} username={sub.username} phone={sub.phone} />
                                                  </td>
                                                  <td className="px-4 py-3 text-right text-muted-foreground">—</td>
                                                  <FigureCells row={sub} />
                                                  <td className="px-4 py-3">
                                                      <StatusBadge status={sub.isActive ? 'active' : 'suspended'} />
                                                  </td>
                                                  <td className="whitespace-nowrap px-4 py-3 text-right">
                                                      <Link
                                                          href={profitSplits.url({ query: { agent: agent.id, subagent: sub.id } })}
                                                          className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                                                      >
                                                          View splits
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
