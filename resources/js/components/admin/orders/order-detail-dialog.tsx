import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyOrder,
    markVerified as markOrderVerified,
    poll as pollOrder,
    refund as refundOrder,
    verifyPayment as verifyOrderPayment,
} from '@/actions/App/Http/Controllers/Admin/OrdersController';
import { SellerTypeBadge } from '@/components/admin/orders/seller-type-badge';
import { StatusBadge } from '@/components/common/status-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cedis } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface AdminOrder {
    id: number;
    reference: string;
    seller: string;
    sellerType: string;
    network: string;
    capacityGb: number;
    beneficiary: string;
    channel: string;
    source: string;
    paymentStatus: string;
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

export function OrderDetailDialog({
    order,
    segment = 'agent',
    onClose,
}: {
    order: AdminOrder | null;
    segment?: 'agent' | 'regular';
    onClose: () => void;
}) {
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
                        {order.paymentStatus !== 'paid' ? (
                            <span
                                className={
                                    order.paymentStatus === 'awaiting'
                                        ? 'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                        : 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300'
                                }
                            >
                                {order.paymentStatus === 'awaiting' ? 'Awaiting payment' : 'Payment failed'}
                            </span>
                        ) : null}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-5">
                    <section>
                        <p className="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
                            <span className="font-medium text-foreground">{order.seller}</span>
                            <SellerTypeBadge type={order.sellerType} />
                            sold {order.network} {order.capacityGb}GB to {order.beneficiary} · {order.channel} · via{' '}
                            {order.source === 'api' ? 'API' : 'portal'}
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
                            <p
                                className={cn(
                                    'mt-2 text-sm',
                                    order.status === 'failed'
                                        ? 'text-danger'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {upstream.failureReason}
                            </p>
                        ) : null}
                    </section>

                    {segment === 'regular' && order.paymentStatus === 'awaiting' ? (
                        <div className="space-y-2 rounded-lg border border-amber-400/50 p-4">
                            <p className="text-sm text-muted-foreground">
                                This storefront order is awaiting payment. Verify checks the gateway (cleared →
                                dispatch, failed → mark failed). Mark verified confirms it manually and dispatches.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    className="flex-1"
                                    onClick={() =>
                                        router.post(verifyOrderPayment(order.id).url, {}, {
                                            preserveScroll: true,
                                            onSuccess: onClose,
                                        })
                                    }
                                >
                                    Verify payment
                                </Button>
                                <Button
                                    variant="secondary"
                                    className="flex-1"
                                    onClick={() =>
                                        router.post(markOrderVerified(order.id).url, {}, {
                                            preserveScroll: true,
                                            onSuccess: onClose,
                                        })
                                    }
                                >
                                    Mark verified
                                </Button>
                            </div>
                            <Button
                                variant="ghost"
                                className="w-full text-danger hover:text-danger"
                                onClick={() =>
                                    router.delete(destroyOrder(order.id).url, {
                                        preserveScroll: true,
                                        onSuccess: onClose,
                                    })
                                }
                            >
                                Delete order
                            </Button>
                        </div>
                    ) : null}

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
