import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/common/status-badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cedis } from '@/lib/format';
import type { Account } from './types';

// Read-only snapshot of one account. Everything shown is already on the row, so no extra request.
export function AccountDetailsDialog({ account, onClose }: { account: Account | null; onClose: () => void }) {
    return (
        <Dialog open={account !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{account?.name}</DialogTitle>
                </DialogHeader>

                {account ? (
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <Row label="Phone" value={account.phone} />
                        <Row label="Username" value={account.username ?? '—'} />
                        <Row label="Email" value={account.email ?? '—'} />
                        <Row label="Status" value={<StatusBadge status={account.status} />} />
                        <Row label="Detail" value={account.detail} span />
                        <Row label="Deposit wallet" value={cedis(account.wallet)} />
                        <Row label="Earnings" value={cedis(account.earnings)} />
                        <Row label="Orders" value={String(account.ordersCount)} />
                        <Row label="Orders value" value={cedis(account.ordersTotal)} />
                        <Row label="Last activity" value={account.lastActivity ?? 'Never'} />
                        <Row label="Joined" value={account.createdAt ?? '—'} />
                    </dl>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function Row({ label, value, span }: { label: string; value: ReactNode; span?: boolean }) {
    return (
        <div className={span ? 'col-span-2' : undefined}>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 font-medium">{value}</dd>
        </div>
    );
}
