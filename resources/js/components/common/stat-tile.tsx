import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * A single headline metric: quiet uppercase label, large tabular number, optional hint.
 * The data is the hero (ENGINEERING_PRINCIPLES §2).
 */
export function StatTile({ label, value, hint }: { label: string; value: string; hint?: ReactNode }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold tabular-nums tracking-tight">{value}</div>
                {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
            </CardContent>
        </Card>
    );
}
