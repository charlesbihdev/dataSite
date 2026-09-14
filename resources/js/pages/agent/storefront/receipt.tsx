import { Head, Link } from "@inertiajs/react";
import { CheckCircle2, Clock, XCircle } from "lucide-react";
import { show, track } from "@/actions/App/Http/Controllers/Storefront/StorefrontController";
import { Button } from "@/components/ui/button";
import { cedis } from "@/lib/format";

interface Props {
    agentSlug: string;
    store: { name: string; whatsapp: string | null; whatsappGroup: string | null };
    order: {
        reference: string;
        network: string;
        networkLabel: string | null;
        capacityGb: number;
        phone: string;
        amount: number;
        paymentStatus: "paid" | "awaiting" | "failed";
        status: string;
    };
}

const STATE = {
    paid: { icon: CheckCircle2, tone: "text-success", title: "Payment received", note: "Your bundle is on its way." },
    awaiting: { icon: Clock, tone: "text-brand", title: "Order received", note: "We're confirming your payment. Your bundle will be delivered shortly." },
    failed: { icon: XCircle, tone: "text-destructive", title: "Payment failed", note: "We couldn't confirm your payment. Please try again." },
} as const;

export default function StorefrontReceipt({ agentSlug, store, order }: Props) {
    const state = STATE[order.paymentStatus] ?? STATE.awaiting;
    const Icon = state.icon;

    return (
        <>
            <Head title={`Order ${order.reference} · ${store.name}`} />
            <div className="flex min-h-screen items-center justify-center bg-background px-4 py-12 text-foreground">
                <div className="w-full max-w-md">
                    <div className="rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
                        <Icon className={`mx-auto size-14 ${state.tone}`} />
                        <h1 className="mt-4 text-2xl font-bold tracking-tight">{state.title}</h1>
                        <p className="mt-2 text-sm text-muted-foreground">{state.note}</p>

                        <dl className="mt-6 space-y-3 rounded-xl border border-border bg-muted/40 p-4 text-left text-sm">
                            <Row label="Reference" value={order.reference} mono />
                            <Row label="Bundle" value={`${order.networkLabel} ${order.capacityGb}GB`} />
                            <Row label="Recipient" value={order.phone} mono />
                            <div className="flex items-center justify-between border-t border-border pt-3">
                                <dt className="font-medium text-muted-foreground">Amount</dt>
                                <dd className="text-lg font-bold tabular-nums">{cedis(order.amount)}</dd>
                            </div>
                        </dl>

                        <div className="mt-6 grid gap-2 sm:grid-cols-2">
                            <Button asChild variant="outline">
                                <Link href={show.url({ agentSlug })}>Buy another bundle</Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={track.url({ agentSlug })}>Track my orders</Link>
                            </Button>
                        </div>

                        {store.whatsapp && (
                            <a
                                href={`https://wa.me/${store.whatsapp.replace(/\D+/g, "")}`}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-3 inline-block text-xs font-medium text-brand hover:underline"
                            >
                                Need help? Contact {store.name}
                            </a>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function Row({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="flex items-center justify-between">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={mono ? "font-mono text-xs" : "font-medium"}>{value}</dd>
        </div>
    );
}
