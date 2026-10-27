import { usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { AlertTriangle, X } from "lucide-react";

interface SkippedRow {
    row: number;
    phone: string;
    size: string;
    reason: string;
}

/**
 * Lines from a bulk paste / Excel upload that were NOT added to the cart, with the reason for each.
 * The server sends them once (flash); we keep them on screen until dismissed or the next upload.
 */
export function SkippedRows({ rowLabel }: { rowLabel: string }) {
    const flashed = (usePage().flash as { cartSkipped?: SkippedRow[] } | undefined)?.cartSkipped;
    const [rows, setRows] = useState<SkippedRow[]>([]);

    useEffect(() => {
        if (flashed) {
            setRows(flashed);
        }
    }, [flashed]);

    if (rows.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border border-destructive/30 bg-destructive/5">
            <div className="flex items-center justify-between gap-2 border-b border-destructive/20 px-3 py-2">
                <p className="flex items-center gap-2 text-sm font-medium text-destructive">
                    <AlertTriangle className="size-4" />
                    {rows.length} {rows.length === 1 ? "line was" : "lines were"} not added
                </p>
                <button
                    type="button"
                    onClick={() => setRows([])}
                    className="text-muted-foreground transition hover:text-foreground"
                    aria-label="Dismiss"
                >
                    <X className="size-4" />
                </button>
            </div>
            <ul className="max-h-60 divide-y divide-border overflow-y-auto text-xs">
                {rows.map((r) => (
                    <li key={`${r.row}-${r.phone}`} className="grid grid-cols-[auto_1fr] gap-x-3 px-3 py-2">
                        <span className="text-muted-foreground">
                            {rowLabel} {r.row}
                        </span>
                        <span>
                            <span className="font-mono">{r.phone || "—"}</span>
                            <span className="text-muted-foreground"> · {r.size ? `${r.size}GB` : "no size"}</span>
                            <span className="block text-destructive">{r.reason}</span>
                        </span>
                    </li>
                ))}
            </ul>
            <p className="border-t border-destructive/20 px-3 py-2 text-xs text-muted-foreground">
                Fix these and add them again — nothing here was charged or placed.
            </p>
        </div>
    );
}
