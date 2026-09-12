import type { ReactNode } from 'react';

/**
 * Calm placeholder for an empty list or table cell area.
 */
export function EmptyState({ message, action }: { message: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 py-10 text-center">
            <p className="text-sm text-muted-foreground">{message}</p>
            {action}
        </div>
    );
}
