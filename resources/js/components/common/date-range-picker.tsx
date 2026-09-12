import { CalendarDays, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { RangeCalendar } from '@/components/common/range-calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export interface DateRangeValue {
    range: string;
    from?: string | null;
    to?: string | null;
}

const PRESETS: ([string, string] | 'divider')[] = [
    ['today', 'Today'],
    ['yesterday', 'Yesterday'],
    'divider',
    ['last_7_days', 'Last 7 days'],
    ['last_30_days', 'Last 30 days'],
    ['last_90_days', 'Last 90 days'],
    'divider',
    ['this_week', 'This week'],
    ['last_week', 'Last week'],
    ['this_month', 'This month'],
    ['last_month', 'Last month'],
    ['this_year', 'This year'],
    ['last_year', 'Last year'],
    'divider',
    ['all', 'All time'],
];

function labelFor(value: DateRangeValue): string {
    if (value.range === 'custom') {
        return value.from && value.to ? `${value.from} → ${value.to}` : 'Custom range';
    }
    const preset = PRESETS.find((p): p is [string, string] => p !== 'divider' && p[0] === value.range);

    return preset ? preset[1] : 'All time';
}

/**
 * Date filter: a trigger showing the active range, opening a popover of quick presets alongside
 * a calendar for a bespoke range. Every choice bubbles up so the caller reloads with it as a
 * query filter. Ported from the biizz platform picker onto DataSite's tokens.
 */
export function DateRangePicker({ value, onChange }: { value: DateRangeValue; onChange: (next: DateRangeValue) => void }) {
    const [open, setOpen] = useState(false);

    const go = (next: DateRangeValue) => {
        setOpen(false);
        onChange(next);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="inline-flex shrink-0 items-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground shadow-sm transition hover:bg-muted"
                >
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {labelFor(value)}
                    <ChevronDown className="size-4 text-muted-foreground" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="flex flex-col p-0 sm:flex-row">
                <div className="flex max-h-72 min-w-44 flex-col gap-0.5 overflow-y-auto p-1.5 sm:border-r sm:border-border">
                    {PRESETS.map((preset, index) =>
                        preset === 'divider' ? (
                            <div key={`d-${index}`} className="my-1 border-t border-border" />
                        ) : (
                            <button
                                key={preset[0]}
                                type="button"
                                onClick={() => go({ range: preset[0], from: null, to: null })}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-left text-sm transition',
                                    value.range === preset[0]
                                        ? 'bg-brand/10 font-medium text-brand'
                                        : 'text-foreground hover:bg-muted',
                                )}
                            >
                                {preset[1]}
                            </button>
                        ),
                    )}
                </div>
                <RangeCalendar
                    from={value.from ?? null}
                    to={value.to ?? null}
                    onSelect={(from, to) => go({ range: 'custom', from, to })}
                />
            </PopoverContent>
        </Popover>
    );
}
