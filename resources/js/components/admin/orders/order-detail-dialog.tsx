import { router } from '@inertiajs/react';
import { useState } from 'react';
import { poll as pollOrder, refund as refundOrder } from '@/actions/App/Http/Controllers/Admin/OrdersController';
import { StatusBadge } from '@/components/common/status-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cedis } from '@/lib/format';

export interface AdminOrder {
    id: number;
    reference: string;
    seller: string;
    network: string;
    capacityGb: number;
    beneficiary: string;
    channel: string;
    status: string;
    cascade: {
        customerPrice: number;
        sellerCost: number;
        agentCost: number;
        baseCost: number | null;
        sellerProfit: number;
        agentCommission: number;
        platformProfit: number | null;
    };
    upstream: {
        requestId: string | null;
        reference: string | null;
        status: string | null;
        cost: number | null;
        lastPolledAt: string | null;
        failureReason: string | null;
    };
    createdAt: string | null;
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
    return (
        <div className="flex items-center justify-between py-1.5 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className={strong ? 'font-semibold tabular-nums' : 'tabular-nums'}>{value}</span>
        </div>
    );
}

export function OrderDetailDialog({ order, onClose }: { order: AdminOrder | null; onClose: () => void }) {
    const [refunding, setRefunding] = useState(false);
    const [reason, setReason] = useState('');

    if (order === null) {
        return null;
    }

    const { cascade, upstream } = order;

    const submitRefund = () => {
        router.post(
            refundOrder(order.id).url,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRefunding(false);
                    setReason('');
                    onClose();
                },
            },
        );
    };

    return (
        <Dialog open={order !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span className="font-mono text-sm">{order.reference}</span>
                        <StatusBadge status={order.status} />
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-5">
                    <section>
                        <p className="text-sm text-muted-foreground">
                            {order.seller} sold {order.network} {order.capacityGb}GB to {order.beneficiary} · {order.channel}
                        </p>
                    </section>

                    <section className="rounded-lg border border-border p-4">
                        <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Frozen cascade
                        </h3>
                        <Row label="Customer price" value={cedis(cascade.customerPrice)} strong />
                        <Row label="Seller cost" value={cedis(cascade.sellerCost)} />
                        <Row label="Agent cost" value={cedis(cascade.agentCost)} />
                        <Row label="Base cost" value={cascade.baseCost !== null ? cedis(cascade.baseCost) : '—'} />
                    </section>

                    <section className="rounded-lg border border-border p-4">
                        <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Profit split
                        </h3>
                        <Row label="Seller profit" value={cedis(cascade.sellerProfit)} />
                        <Row label="Agent commission" value={cedis(cascade.agentCommission)} />
                        <Row
                            label="Platform profit"
                            value={cascade.platformProfit !== null ? cedis(cascade.platformProfit) : '—'}
                            strong
                        />
                    </section>

                    <section className="rounded-lg border border-border p-4">
                        <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Upstream (Databundleshub)
                        </h3>
                        <Row label="Request ID" value={upstream.requestId ?? '—'} />
                        <Row label="Reference" value={upstream.reference ?? '—'} />
                        <Row label="Reported status" value={upstream.status ?? '—'} />
                        <Row label="Actual cost" value={upstream.cost !== null ? cedis(upstream.cost) : '—'} />
                        <Row label="Last polled" value={upstream.lastPolledAt ?? '—'} />
                        {upstream.failureReason ? (
                            <p className="mt-2 text-sm text-danger">{upstream.failureReason}</p>
                        ) : null}
                    </section>

                    {order.status === 'processing' && upstream.requestId ? (
                        <Button
                            onClick={() => router.post(pollOrder(order.id).url, {}, { preserveScroll: true })}
                            className="w-full"
                        >
                            Re-poll upstream status
                        </Button>
                    ) : null}

                    {order.status === 'completed' ? (
                        refunding ? (
                            <div className="space-y-2 rounded-lg border border-danger/40 p-4">
                                <p className="text-sm text-muted-foreground">
                                    Refund returns {cedis(cascade.sellerCost)} to the seller and reverses this order's
                                    earnings. This can't be undone.
                                </p>
                                <Input
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    placeholder="Reason (optional) — e.g. data never delivered"
                                />
                                <div className="flex gap-2">
                                    <Button variant="destructive" className="flex-1" onClick={submitRefund}>
                                        Confirm refund
                                    </Button>
                                    <Button variant="secondary" className="flex-1" onClick={() => setRefunding(false)}>
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <Button variant="outline" className="w-full" onClick={() => setRefunding(true)}>
                                Refund order
                            </Button>
                        )
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}
