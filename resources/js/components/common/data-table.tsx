import type { ReactNode } from 'react';
import { EmptyState } from '@/components/common/empty-state';
import { cn } from '@/lib/utils';

export interface Column<T> {
    key: string;
    header: ReactNode;
    render?: (row: T) => ReactNode;
    align?: 'left' | 'right';
    className?: string;
}

/**
 * The shared table for every admin list, in the house style: a rounded bordered card with a
 * shaded header band, roomy rows divided by hairlines, a hover highlight, and horizontal scroll
 * on narrow screens. Columns declare their own renderers so pages stay thin.
 */
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    emptyMessage = 'Nothing here yet.',
    selectedIds,
    onSelectionChange,
    isRowSelectable,
}: {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    emptyMessage?: string;
    // Opt-in multi-select: pass onSelectionChange to render a leading checkbox column.
    selectedIds?: (string | number)[];
    onSelectionChange?: (ids: (string | number)[]) => void;
    isRowSelectable?: (row: T) => boolean;
}) {
    const selectable = typeof onSelectionChange === 'function';
    const selected = new Set(selectedIds ?? []);
    const canSelect = (row: T) => !isRowSelectable || isRowSelectable(row);
    const selectableRows = rows.filter(canSelect);
    const allSelected = selectableRows.length > 0 && selectableRows.every((r) => selected.has(rowKey(r)));

    const toggle = (id: string | number) => {
        const next = new Set(selected);
        next.has(id) ? next.delete(id) : next.add(id);
        onSelectionChange?.([...next]);
    };
    const toggleAll = () => onSelectionChange?.(allSelected ? [] : selectableRows.map(rowKey));

    if (rows.length === 0) {
        return (
            <div className="rounded-xl border border-border">
                <EmptyState message={emptyMessage} />
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            {selectable ? (
                                <th className="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        aria-label="Select all"
                                        className="size-4 cursor-pointer accent-primary"
                                        checked={allSelected}
                                        disabled={selectableRows.length === 0}
                                        onChange={toggleAll}
                                    />
                                </th>
                            ) : null}
                            {columns.map((col) => (
                                <th
                                    key={col.key}
                                    className={cn(
                                        'whitespace-nowrap px-4 py-3 font-semibold',
                                        col.align === 'right' && 'text-right',
                                        col.className,
                                    )}
                                >
                                    {col.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={rowKey(row)}
                                className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                {selectable ? (
                                    <td className="w-10 px-4 py-4 align-middle">
                                        {canSelect(row) ? (
                                            <input
                                                type="checkbox"
                                                aria-label="Select row"
                                                className="size-4 cursor-pointer accent-primary"
                                                checked={selected.has(rowKey(row))}
                                                onChange={() => toggle(rowKey(row))}
                                            />
                                        ) : null}
                                    </td>
                                ) : null}
                                {columns.map((col) => (
                                    <td
                                        key={col.key}
                                        className={cn(
                                            'whitespace-nowrap px-4 py-4 align-middle',
                                            col.align === 'right' && 'text-right tabular-nums',
                                            col.className,
                                        )}
                                    >
                                        {col.render ? col.render(row) : String((row as Record<string, unknown>)[col.key] ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
