import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ledger as ledgerRoute } from '@/actions/App/Http/Controllers/Admin/TransactionsController';
import { Column, DataTable } from '@/components/common/data-table';
import { PageHeader } from '@/components/common/page-header';
import { PageLink, Pagination } from '@/components/common/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cedis } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Txn {
    id: number;
    owner: string;
    type: string;
    amount: number;
    balanceAfter: number;
    reference: string | null;
    description: string | null;
    createdAt: string | null;
}

interface Props {
    transactions: { data: Txn[]; links: PageLink[] };
    filters: { q: string };
}

export default function AdminLedger({ transactions, filters }: Props) {
    const [search, setSearch] = useState(filters.q);

    const columns: Column<Txn>[] = [
        { key: 'owner', header: 'Account' },
        { key: 'type', header: 'Type', render: (t) => <span className="capitalize">{t.type.replace('_', ' ')}</span> },
        {
            key: 'amount',
            header: 'Amount',
            align: 'right',
            render: (t) => <span className={cn(t.amount < 0 ? 'text-danger' : 'text-success')}>{cedis(t.amount)}</span>,
        },
        { key: 'balanceAfter', header: 'Balance', align: 'right', render: (t) => cedis(t.balanceAfter) },
        { key: 'description', header: 'Description', render: (t) => <span className="text-muted-foreground">{t.description ?? '—'}</span> },
        { key: 'createdAt', header: 'When', render: (t) => <span className="text-muted-foreground">{t.createdAt ?? '—'}</span> },
    ];

    return (
        <>
            <Head title="Admin — Ledger" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader title="Ledger" description="Every wallet movement — the audit trail behind cached balances." />
                <form
                    className="flex items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(ledgerRoute.url(), { q: search }, { preserveState: true, replace: true });
                    }}
                >
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Reference or description…"
                        className="w-64"
                    />
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>
                <DataTable columns={columns} rows={transactions.data} rowKey={(t) => t.id} emptyMessage="No transactions." />
                <Pagination links={transactions.links} />
            </div>
        </>
    );
}
