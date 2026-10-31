import { exportSplits } from '@/actions/App/Http/Controllers/Admin/ResellersController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { AgentOption, SplitFilters as Filters } from './types';

const SETTLEMENTS = ['all', 'credited', 'pending', 'reversed'];

const SCOPES: { value: Filters['scope']; label: string }[] = [
    { value: 'own', label: 'Their own sales' },
    { value: 'subagents', label: "Subagents' sales" },
];

/** Filter bar for Profit Splits. The subagent list narrows to the chosen agent's subagents; own sales have none. */
export function SplitFilters({
    filters,
    agents,
    search,
    onSearchChange,
    onApply,
}: {
    filters: Filters;
    agents: AgentOption[];
    search: string;
    onSearchChange: (value: string) => void;
    onApply: (patch: Partial<Filters>) => void;
}) {
    const subagents = agents.find((a) => String(a.id) === filters.agent)?.subagents ?? [];
    const exportQuery = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== null && v !== '' && v !== 'all' && v !== false),
    ) as Record<string, string | boolean>;

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="inline-flex rounded-lg border border-border p-0.5">
                {SCOPES.map((scope) => (
                    <button
                        key={scope.value}
                        type="button"
                        aria-pressed={filters.scope === scope.value}
                        onClick={() => onApply({ scope: scope.value, subagent: 'all' })}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            filters.scope === scope.value ? 'bg-brand text-brand-fg' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {scope.label}
                    </button>
                ))}
            </div>

            <Select value={filters.agent} onValueChange={(v) => onApply({ agent: v, subagent: 'all' })}>
                <SelectTrigger className="w-48">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All agents</SelectItem>
                    {agents.map((a) => (
                        <SelectItem key={a.id} value={String(a.id)}>
                            {a.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={filters.subagent} onValueChange={(v) => onApply({ subagent: v })} disabled={filters.agent === 'all' || filters.scope === 'own'}>
                <SelectTrigger className="w-48">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All subagents</SelectItem>
                    {subagents.map((s) => (
                        <SelectItem key={s.id} value={String(s.id)}>
                            {s.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={filters.settlement} onValueChange={(v) => onApply({ settlement: v })}>
                <SelectTrigger className="w-40">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {SETTLEMENTS.map((s) => (
                        <SelectItem key={s} value={s} className="capitalize">
                            {s === 'all' ? 'All settlements' : s}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="problems-only"
                    checked={filters.problems}
                    onCheckedChange={(checked) => onApply({ problems: checked === true })}
                />
                <Label htmlFor="problems-only" className="cursor-pointer text-sm font-normal">
                    Problems only
                </Label>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="profit-only"
                    checked={filters.profit_only}
                    onCheckedChange={(checked) => onApply({ profit_only: checked === true })}
                />
                <Label htmlFor="profit-only" className="cursor-pointer text-sm font-normal">
                    Only sales with profit
                </Label>
            </div>

            <Input
                value={search}
                onChange={(e) => onSearchChange(e.target.value)}
                placeholder="Order reference, subagent or agent…"
                className="ml-auto w-72"
            />

            <Button asChild variant="outline" size="sm">
                <a href={exportSplits.url({ query: exportQuery })}>Export CSV</a>
            </Button>
        </div>
    );
}
