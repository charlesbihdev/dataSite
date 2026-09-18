import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { STATUSES } from './orders-shared';

export type BulkAction = 'verify' | 'mark-verified' | 'delete' | 'sync' | 'retry' | 'apply-status';

/**
 * Bulk-action bar for the admin order pages. The Regular (storefront) page gets the payment-flow
 * actions; the Agent page gets sync. The manual status override is shown on BOTH — the caller
 * confirms the risky storefront "complete" case in its runBulk handler.
 */
export function OrderBulkBar({
    isRegular,
    count,
    applyStatus,
    onApplyStatusChange,
    onRun,
    onClear,
}: {
    isRegular: boolean;
    count: number;
    applyStatus: string;
    onApplyStatusChange: (value: string) => void;
    onRun: (action: BulkAction, status?: string) => void;
    onClear: () => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-muted/40 px-4 py-2">
            <span className="text-sm font-medium">{count} selected</span>
            <div className="ml-auto flex flex-wrap gap-2">
                {isRegular ? (
                    <>
                        <Button size="sm" onClick={() => onRun('verify')}>
                            Verify payment
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => onRun('mark-verified')}>
                            Mark verified
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => onRun('retry')}>
                            Retry dispatch
                        </Button>
                        <Button size="sm" variant="ghost" className="text-danger hover:text-danger" onClick={() => onRun('delete')}>
                            Delete
                        </Button>
                    </>
                ) : (
                    <>
                        <Button size="sm" onClick={() => onRun('sync')}>
                            Sync status
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => onRun('retry')}>
                            Retry dispatch
                        </Button>
                    </>
                )}

                <div className="flex items-center gap-1">
                    <Select value={applyStatus} onValueChange={onApplyStatusChange}>
                        <SelectTrigger className="h-8 w-36">
                            <SelectValue placeholder="Set status…" />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUSES.filter((s) => s !== 'all').map((s) => (
                                <SelectItem key={s} value={s} className="capitalize">
                                    {s}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button size="sm" variant="secondary" disabled={!applyStatus} onClick={() => onRun('apply-status', applyStatus)}>
                        Apply
                    </Button>
                </div>

                <Button size="sm" variant="ghost" onClick={onClear}>
                    Clear
                </Button>
            </div>
        </div>
    );
}
