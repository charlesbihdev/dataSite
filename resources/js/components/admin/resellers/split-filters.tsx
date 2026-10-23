import { exportSplits } from '@/actions/App/Http/Controllers/Admin/ResellersController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { AgentOption, SplitFilters as Filters } from './types';

const SETTLEMENTS = ['all', 'credited', 'pending', 'reversed'];

/** Filter bar for Profit Splits. The subagent list narrows to the chosen agent's subagents. */
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

            <Select value={filters.subagent} onValueChange={(v) => onApply({ subagent: v })} disabled={filters.agent === 'all'}>
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
