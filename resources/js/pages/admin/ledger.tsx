import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { ledger as ledgerRoute } from "@/actions/App/Http/Controllers/Admin/TransactionsController";
import { Amount } from "@/components/common/amount";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cedis } from "@/lib/format";

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

interface Stats {
    total_transactions: number;
    credit_transactions: number;
    debit_transactions: number;
    total_credits: number;
    total_debits: number;
    net_change: number;
}

interface Props {
    transactions: { data: Txn[]; links: PageLink[] };
    filters: { q: string; type?: string; date_from?: string; date_to?: string };
    stats: Stats;
}

export default function AdminLedger({ transactions, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.q || "");
    const [type, setType] = useState(filters.type || "all");
    const [dateFrom, setDateFrom] = useState(filters.date_from || "");
    const [dateTo, setDateTo] = useState(filters.date_to || "");

    const columns: Column<Txn>[] = [
        { key: "owner", header: "Account" },
        {
            key: "type",
            header: "Type",
            render: (t) => (
                <span className="capitalize">{t.type.replace("_", " ")}</span>
            ),
        },
        {
            key: "amount",
            header: "Amount",
            align: "right",
            render: (t) => <Amount value={t.amount} />,
        },
        {
            key: "balanceAfter",
            header: "Balance",
            align: "right",
            render: (t) => cedis(t.balanceAfter),
        },
        {
            key: "description",
            header: "Description",
            render: (t) => (
                <span className="text-muted-foreground">
                    {t.description ?? "—"}
                </span>
            ),
        },
        {
            key: "createdAt",
            header: "When",
            render: (t) => (
                <span className="text-muted-foreground">
                    {t.createdAt ?? "—"}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Admin — Ledger" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Ledger"
                    description="Every wallet movement — the audit trail behind cached balances."
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Total Transactions"
                        value={String(stats.total_transactions)}
                        hint={`${stats.credit_transactions} credits, ${stats.debit_transactions} debits`}
                    />
                    <StatTile
                        label="Total Credits"
                        value={cedis(stats.total_credits)}
                        hint="Money flowing in"
                    />
                    <StatTile
                        label="Total Debits"
                        value={cedis(stats.total_debits)}
                        hint="Money flowing out"
                    />
                    <StatTile
                        label="Net Change"
                        value={cedis(stats.net_change)}
                        hint="Balance impact"
                    />
                </div>

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            ledgerRoute.url(),
                            {
                                q: search,
                                type: type === "all" ? "" : type,
                                date_from: dateFrom,
                                date_to: dateTo,
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            <SelectItem value="topup">Top-ups</SelectItem>
                            <SelectItem value="order">Orders</SelectItem>
                            <SelectItem value="commission">
                                Commissions
                            </SelectItem>
                            <SelectItem value="refund">Refunds</SelectItem>
                            <SelectItem value="reversal">Reversals</SelectItem>
                        </SelectContent>
                    </Select>

                    <Input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        className="w-40"
                    />
                    <span className="text-sm text-muted-foreground">to</span>
                    <Input
                        type="date"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        className="w-40"
                    />

                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Name, email, reference…"
                        className="w-64"
                    />
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>
                <DataTable
                    columns={columns}
                    rows={transactions.data}
                    rowKey={(t) => t.id}
                    emptyMessage="No transactions."
                />
                <Pagination links={transactions.links} />
            </div>
        </>
    );
}
