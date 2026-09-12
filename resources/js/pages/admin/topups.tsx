import { Head } from '@inertiajs/react';
import { Column, DataTable } from '@/components/common/data-table';
import { PageHeader } from '@/components/common/page-header';
import { PageLink, Pagination } from '@/components/common/pagination';
import { cedis } from '@/lib/format';

interface Topup {
    id: number;
    owner: string;
    amount: number;
    balanceAfter: number;
    reference: string | null;
    createdAt: string | null;
}

export default function AdminTopups({ topups }: { topups: { data: Topup[]; links: PageLink[] } }) {
    const columns: Column<Topup>[] = [
        { key: 'owner', header: 'Account' },
        { key: 'amount', header: 'Amount', align: 'right', render: (t) => <span className="text-success">{cedis(t.amount)}</span> },
        { key: 'balanceAfter', header: 'Balance after', align: 'right', render: (t) => cedis(t.balanceAfter) },
        { key: 'reference', header: 'Reference', render: (t) => <span className="font-mono text-xs">{t.reference ?? '—'}</span> },
        { key: 'createdAt', header: 'When', render: (t) => <span className="text-muted-foreground">{t.createdAt ?? '—'}</span> },
    ];

    return (
        <>
            <Head title="Admin — Top-ups" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader title="Top-ups" description="Deposits into agent and subagent wallets." />
                <DataTable columns={columns} rows={topups.data} rowKey={(t) => t.id} emptyMessage="No top-ups yet." />
                <Pagination links={topups.links} />
            </div>
        </>
    );
}
