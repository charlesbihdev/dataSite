import type { ReactNode } from "react";
import { Link } from "@inertiajs/react";
import { Amount } from "@/components/common/amount";
import { StatusBadge } from "@/components/common/status-badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { cedis } from "@/lib/format";

export interface Txn {
    id: number;
    type: string;
    direction: "credit" | "debit";
    source: "user" | "admin";
    amount: number;
    orderReference: string | null;
    paymentSource: string | null;
    status: string;
    balanceBefore: number;
    balanceAfter: number;
    code: string | null;
    date: string | null;
}

// Read-only detail of one ledger row. Everything shown is already on the row, so no extra request.
export function TransactionDetailDialog({ transaction, onClose }: { transaction: Txn | null; onClose: () => void }) {
    return (
        <Dialog open={transaction !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{transaction?.type}</DialogTitle>
                </DialogHeader>

                {transaction ? (
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <Row label="Amount" value={<Amount value={transaction.amount} signed />} />
                        <Row label="Source" value={<span className="uppercase">{transaction.source}</span>} />
                        <Row label="Status" value={<StatusBadge status={transaction.status} />} />
                        <Row label="Payment Src" value={transaction.paymentSource ?? "—"} />
                        <Row label="Balance before" value={cedis(transaction.balanceBefore)} />
                        <Row label="Balance after" value={cedis(transaction.balanceAfter)} />
                        <Row label="Transaction code" value={<span className="font-mono text-xs">{transaction.code ?? "—"}</span>} />
                        <Row label="Date" value={transaction.date ?? "—"} />
                        <Row
                            label="Order"
                            span
                            value={
                                transaction.orderReference ? (
                                    <Link href={`/orders?q=${transaction.orderReference}`} className="font-mono text-xs text-brand hover:underline">
                                        {transaction.orderReference}
                                    </Link>
                                ) : (
                                    "—"
                                )
                            }
                        />
                    </dl>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function Row({ label, value, span }: { label: string; value: ReactNode; span?: boolean }) {
    return (
        <div className={span ? "col-span-2" : undefined}>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 font-medium">{value}</dd>
        </div>
    );
}
