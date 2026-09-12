import { router } from '@inertiajs/react';
import { bulk, exportCsv } from '@/actions/App/Http/Controllers/Admin/AccountsController';
import { Button } from '@/components/ui/button';
import type { AccountType } from './types';

type BulkAction = 'reset' | 'suspend' | 'activate' | 'delete';

// Appears when rows are selected. Export is a plain GET download; the rest post and reload.
export function AccountsToolbar({
    type,
    selectedIds,
    onDone,
}: {
    type: AccountType;
    selectedIds: number[];
    onDone: () => void;
}) {
    const count = selectedIds.length;

    const run = (action: BulkAction) => {
        const data: { action: BulkAction; ids: number[]; password?: string } = { action, ids: selectedIds };

        if (action === 'reset') {
            const password = window.prompt('New password for the selected accounts (min 8 chars):');
            if (!password) return;
            data.password = password;
        }
        if (action === 'delete' && !window.confirm(`Delete ${count} account(s)? Accounts with orders are kept.`)) {
            return;
        }

        router.post(bulk({ type }).url, data, { preserveScroll: true, onSuccess: onDone });
    };

    // A file download is a genuine full-page GET, so it uses a native anchor — not <Link> or
    // router (which expect an Inertia response).
    const exportUrl = exportCsv({ type }, { query: { ids: selectedIds.join(',') } }).url;

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2">
            <span className="text-sm font-medium">{count} selected</span>
            <div className="ml-auto flex flex-wrap gap-2">
                <Button variant="outline" size="sm" asChild>
                    <a href={exportUrl} download>
                        Export CSV
                    </a>
                </Button>
                <Button variant="outline" size="sm" onClick={() => run('reset')}>
                    Reset password
                </Button>
                <Button variant="outline" size="sm" onClick={() => run('activate')}>
                    Activate
                </Button>
                <Button variant="outline" size="sm" onClick={() => run('suspend')}>
                    Suspend
                </Button>
                <Button variant="outline" size="sm" className="text-danger" onClick={() => run('delete')}>
                    Delete
                </Button>
            </div>
        </div>
    );
}
