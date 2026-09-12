import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

function toKey(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function fromKey(value: string | null): Date | null {
    if (!value) {
        return null;
    }

    const [y, m, d] = value.split('-').map(Number);

    return y && m && d ? new Date(y, m - 1, d) : null;
}

/**
 * A single-month calendar for choosing a custom [from, to] range. The first click sets the
 * start, the second sets the end (a click before the start restarts the selection). Emits
 * inclusive yyyy-mm-dd keys once both ends are chosen.
 */
export function RangeCalendar({
    from,
    to,
    onSelect,
}: {
    from: string | null;
    to: string | null;
    onSelect: (from: string, to: string) => void;
}) {
    const seed = fromKey(from) ?? new Date();
    const [view, setView] = useState(new Date(seed.getFullYear(), seed.getMonth(), 1));
    const [start, setStart] = useState<Date | null>(fromKey(from));
    const [end, setEnd] = useState<Date | null>(fromKey(to));

    const firstWeekday = view.getDay();
    const daysInMonth = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
    const cells: (Date | null)[] = [
        ...Array.from({ length: firstWeekday }, () => null),
        ...Array.from({ length: daysInMonth }, (_, i) => new Date(view.getFullYear(), view.getMonth(), i + 1)),
    ];

    const inRange = (day: Date) => start && end && day >= start && day <= end;
    const isEdge = (day: Date) =>
        (start && toKey(day) === toKey(start)) || (end && toKey(day) === toKey(end));

    const pick = (day: Date) => {
        if (!start || (start && end)) {
            setStart(day);
            setEnd(null);

            return;
        }

        if (day < start) {
            setStart(day);

            return;
        }

        setEnd(day);
        onSelect(toKey(start), toKey(day));
    };

    const shiftMonth = (delta: number) => setView(new Date(view.getFullYear(), view.getMonth() + delta, 1));

    return (
        <div className="w-64 p-3">
            <div className="mb-2 flex items-center justify-between">
                <button
                    type="button"
                    onClick={() => shiftMonth(-1)}
                    className="rounded p-1 text-muted-foreground hover:bg-muted"
                    aria-label="Previous month"
                >
                    <ChevronLeft className="size-4" />
                </button>
                <span className="text-sm font-semibold text-foreground">
                    {MONTHS[view.getMonth()]} {view.getFullYear()}
                </span>
                <button
                    type="button"
                    onClick={() => shiftMonth(1)}
                    className="rounded p-1 text-muted-foreground hover:bg-muted"
                    aria-label="Next month"
                >
                    <ChevronRight className="size-4" />
                </button>
            </div>

            <div className="grid grid-cols-7 gap-0.5 text-center">
                {WEEKDAYS.map((day) => (
                    <span key={day} className="py-1 text-xs font-medium text-muted-foreground">
                        {day}
                    </span>
                ))}
                {cells.map((day, index) =>
                    day === null ? (
                        <span key={`x-${index}`} />
                    ) : (
                        <button
                            key={toKey(day)}
                            type="button"
                            onClick={() => pick(day)}
                            className={cn(
                                'flex h-8 items-center justify-center rounded text-sm transition',
                                inRange(day) && 'bg-brand/10',
                                isEdge(day)
                                    ? 'bg-brand font-semibold text-brand-fg'
                                    : 'text-foreground hover:bg-muted',
                            )}
                        >
                            {day.getDate()}
                        </button>
                    ),
                )}
            </div>
        </div>
    );
}
