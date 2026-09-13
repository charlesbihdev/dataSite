import type { ReactNode } from "react";
import { Amount } from "@/components/common/amount";
import { StatusBadge } from "@/components/common/status-badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { cedis } from "@/lib/format";

export interface Order {
    id: number;
    reference: string;
    created_at: string;
    network: string;
    capacity_gb: string;
    beneficiary_phone: string;
    customer_price: string;
    seller_cost: string;
    payment_status: string;
    status: string;
    upstream_reference: string | null;
    upstream_status: string | null;
}

// Read-only detail of one order. Everything shown is already on the row, so no extra request.
export function OrderDetailDialog({ order, onClose }: { order: Order | null; onClose: () => void }) {
    const profit = order ? Number(order.customer_price) - Number(order.seller_cost) : 0;

    return (
        <Dialog open={order !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-mono text-base">{order?.reference}</DialogTitle>
                </DialogHeader>

                {order ? (
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <Row label="Status" value={<StatusBadge status={order.status} />} />
                        <Row label="Payment" value={<StatusBadge status={order.payment_status} />} />
                        <Row label="Bundle" value={<span className="uppercase">{order.network} {order.capacity_gb}GB</span>} />
                        <Row label="Beneficiary" value={<span className="font-mono">{order.beneficiary_phone}</span>} />
                        <Row label="Amount" value={cedis(order.customer_price)} />
                        <Row label="Your cost" value={cedis(order.seller_cost)} />
                        <Row label="Profit" value={<Amount value={profit} />} />
                        <Row label="Date" value={new Date(order.created_at).toLocaleString()} />
                        <Row label="Upstream ref" value={order.upstream_reference ? <span className="font-mono text-xs">{order.upstream_reference}</span> : "—"} />
                        <Row label="Upstream status" value={order.upstream_status ?? "—"} />
                    </dl>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function Row({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 font-medium">{value}</dd>
        </div>
    );
}
