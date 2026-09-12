import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { update as updateWithdrawal } from "@/actions/App/Http/Controllers/Admin/WithdrawalsController";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { cedis } from "@/lib/format";

interface Withdrawal {
    id: number;
    earner: string;
    amount: number;
    status: string;
    reference: string | null;
    notes: string | null;
    requestedAt: string | null;
    processedAt: string | null;
}

interface Props {
    withdrawals: { data: Withdrawal[]; links: PageLink[] };
    summary: { pending: number; approved: number };
}

export default function AdminWithdrawals({ withdrawals, summary }: Props) {
    const [approving, setApproving] = useState<Withdrawal | null>(null);

    const act = (w: Withdrawal, status: string) =>
        router.put(
            updateWithdrawal(w.id).url,
            { status },
            { preserveScroll: true },
        );

    const columns: Column<Withdrawal>[] = [
        { key: "earner", header: "Account" },
        {
            key: "amount",
            header: "Amount",
            align: "right",
            render: (w) => cedis(w.amount),
        },
        {
            key: "status",
            header: "Status",
            render: (w) => <StatusBadge status={w.status} />,
        },
        {
            key: "requestedAt",
            header: "Requested",
            render: (w) => (
                <span className="text-muted-foreground">
                    {w.requestedAt ?? "—"}
                </span>
            ),
        },
        {
            key: "actions",
            header: "",
            align: "right",
            render: (w) => (
                <div className="flex justify-end gap-1">
                    {w.status === "pending" ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-success hover:text-success hover:bg-success/10"
                            onClick={() => setApproving(w)}
                        >
                            Approve
                        </Button>
                    ) : null}
                    {w.status === "approved" ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => act(w, "paid")}
                        >
                            Mark paid
                        </Button>
                    ) : null}
                    {w.status === "pending" || w.status === "approved" ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-danger hover:text-danger hover:bg-danger/10"
                            onClick={() => act(w, "rejected")}
                        >
                            Reject
                        </Button>
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Admin — Withdrawals" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Withdrawals"
                    description="Approve and pay agent and subagent earnings withdrawals."
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Pending"
                        value={String(summary.pending)}
                        hint="Awaiting approval"
                    />
                    <StatTile
                        label="Approved"
                        value={String(summary.approved)}
                        hint="Awaiting payout"
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={withdrawals.data}
                    rowKey={(w) => w.id}
                    emptyMessage="No withdrawal requests."
                />
                <Pagination links={withdrawals.links} />
            </div>

            <ConfirmDialog
                open={!!approving}
                onOpenChange={(isOpen) => !isOpen && setApproving(null)}
                title="Approve Withdrawal"
                description={`Are you sure you want to approve the withdrawal of ${approving ? cedis(approving.amount) : ""} for ${approving?.earner}?`}
                confirmLabel="Approve"
                variant="success"
                onConfirm={() => {
                    if (approving) act(approving, "approved");
                    setApproving(null);
                }}
            />
        </>
    );
}
