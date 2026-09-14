import { Head, Link, router } from "@inertiajs/react";
import { CheckCircle2, Clock, Hash, Loader2, Package, Phone, Search, ShoppingBag, XCircle } from "lucide-react";
import { FormEvent, useState } from "react";
import { show } from "@/actions/App/Http/Controllers/Storefront/StorefrontController";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { networkBrand } from "@/lib/network-brand";
import { cedis } from "@/lib/format";
import { cn } from "@/lib/utils";

interface TrackedOrder {
    reference: string;
    network: string;
    networkLabel: string | null;
    capacityGb: number;
    phone: string;
    amount: number;
    paymentStatus: "paid" | "awaiting" | "failed";
    status: string;
    date: string | null;
}

type LookupMode = "phone" | "reference";

interface Props {
    agentSlug: string;
    store: { name: string; whatsapp: string | null; whatsappGroup: string | null };
    by: LookupMode;
    phone: string;
    reference: string;
    orders: TrackedOrder[];
}

const PAYMENT_TONE = {
    paid: { label: "Paid", class: "bg-success/10 text-success" },
    awaiting: { label: "Awaiting", class: "bg-brand/10 text-brand" },
    failed: { label: "Failed", class: "bg-destructive/10 text-destructive" },
} as const;

const STATUS_TONE: Record<string, { label: string; icon: typeof CheckCircle2; class: string }> = {
    pending: { label: "Pending", icon: Clock, class: "text-muted-foreground" },
    processing: { label: "Processing", icon: Clock, class: "text-brand" },
    completed: { label: "Delivered", icon: CheckCircle2, class: "text-success" },
    failed: { label: "Failed", icon: XCircle, class: "text-destructive" },
    refunded: { label: "Refunded", icon: XCircle, class: "text-muted-foreground" },
};

export default function StorefrontTrack({ agentSlug, store, by, phone, reference, orders }: Props) {
    const [mode, setMode] = useState<LookupMode>(by);
    const [input, setInput] = useState(by === "reference" ? reference : phone);
    const [searching, setSearching] = useState(false);
    const searched = (by === "reference" ? reference : phone) !== "";
    const submittedValue = by === "reference" ? reference : phone;

    function switchMode(next: LookupMode) {
        if (next === mode) return;
        setMode(next);
        setInput("");
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const trimmed = input.trim();
        if (trimmed === "") return;
        const key = mode === "reference" ? "reference" : "phone";
        router.get(
            window.location.pathname,
            { by: mode, [key]: trimmed },
            {
                preserveState: true,
                onStart: () => setSearching(true),
                onFinish: () => setSearching(false),
            },
        );
    }

    return (
        <>
            <Head title={`Track Orders · ${store.name}`} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border bg-card/60 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-2xl items-center justify-between gap-3 px-4 py-3.5 sm:px-6 sm:py-4">
                        <div className="flex min-w-0 items-center gap-2.5">
                            <span className="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-brand text-brand-fg sm:flex">
                                <ShoppingBag className="size-5" />
                            </span>
                            <span className="truncate text-base font-semibold">{store.name}</span>
                        </div>
                        <Link href={show.url({ agentSlug })} className="shrink-0 text-sm font-medium text-brand hover:underline">
                            ← Buy bundles
                        </Link>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-2xl px-4 py-10 sm:px-6">
                    <div className="text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-subtle">
                            <Search className="size-6 text-brand" />
                        </div>
                        <h1 className="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Track your orders</h1>
                        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                            No account needed. Enter the phone number you bought with and we'll show every order and where it is.
                        </p>
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        className="mt-8 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
                    >
                        <div className="mb-4 inline-flex rounded-lg border border-border bg-muted p-0.5">
                            <ModeTab active={mode === "phone"} icon={Phone} label="Phone number" onClick={() => switchMode("phone")} />
                            <ModeTab active={mode === "reference"} icon={Hash} label="Order reference" onClick={() => switchMode("reference")} />
                        </div>

                        {mode === "phone" ? (
                            <Label htmlFor="track-input" className="flex items-center gap-1.5 text-sm font-semibold">
                                <Phone className="size-4 text-brand" /> Phone number
                            </Label>
                        ) : (
                            <Label htmlFor="track-input" className="flex items-center gap-1.5 text-sm font-semibold">
                                <Hash className="size-4 text-brand" /> Order reference
                            </Label>
                        )}
                        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                            <Input
                                id="track-input"
                                key={mode}
                                type={mode === "phone" ? "tel" : "text"}
                                inputMode={mode === "phone" ? "numeric" : "text"}
                                autoFocus
                                autoCapitalize={mode === "reference" ? "characters" : undefined}
                                placeholder={mode === "phone" ? "0241234567" : "DS-XXXXXXXXXX"}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                className="h-11 flex-1 text-base"
                            />
                            <Button type="submit" size="lg" disabled={searching || input.trim() === ""}>
                                {searching ? (
                                    <Loader2 className="mr-1.5 size-4 animate-spin" />
                                ) : (
                                    <Search className="mr-1.5 size-4" />
                                )}
                                Find orders
                            </Button>
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {mode === "phone"
                                ? "Use the same number you entered at checkout."
                                : "The DS- code shown on your payment confirmation page."}
                        </p>
                    </form>

                    <div className="mt-8">
                        {!searched ? (
                            <EmptyPrompt />
                        ) : orders.length === 0 ? (
                            <NoResults query={submittedValue} mode={by} store={store} />
                        ) : (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    {orders.length} order{orders.length === 1 ? "" : "s"} found for <span className="font-medium text-foreground">{submittedValue}</span>
                                </p>
                                {orders.map((o) => (
                                    <OrderCard key={o.reference} order={o} />
                                ))}
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}

function OrderCard({ order }: { order: TrackedOrder }) {
    const brand = networkBrand(order.network);
    const payment = PAYMENT_TONE[order.paymentStatus] ?? PAYMENT_TONE.awaiting;
    const statusDef = STATUS_TONE[order.status] ?? STATUS_TONE.pending;
    const StatusIcon = statusDef.icon;

    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <div className="flex items-center gap-2.5">
                    {brand.logo ? (
                        <img src={brand.logo} alt={brand.label} className="size-6 rounded object-cover" />
                    ) : (
                        <span className={cn("flex size-6 items-center justify-center rounded text-[9px] font-bold", brand.badge)}>{brand.short}</span>
                    )}
                    <span className="text-sm font-semibold">{order.networkLabel ?? brand.label} {order.capacityGb}GB</span>
                </div>
                <span className="text-lg font-bold tabular-nums">{cedis(order.amount)}</span>
            </div>
            <div className="space-y-2.5 px-4 py-3">
                <Row label="Reference" value={order.reference} mono />
                <Row label="Recipient" value={order.phone} mono />
                {order.date && <Row label="Date" value={order.date} />}
                <div className="flex items-center justify-between pt-1">
                    <div className="flex items-center gap-1.5">
                        <span className="text-xs text-muted-foreground">Payment</span>
                        <span className={cn("rounded-full px-2 py-0.5 text-xs font-medium", payment.class)}>{payment.label}</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <StatusIcon className={cn("size-4", statusDef.class)} />
                        <span className={cn("text-sm font-medium", statusDef.class)}>{statusDef.label}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Row({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className={mono ? "font-mono text-xs" : ""}>{value}</span>
        </div>
    );
}

function EmptyPrompt() {
    return (
        <div className="flex flex-col items-center rounded-2xl border border-dashed border-border bg-card py-14 text-center">
            <ShoppingBag className="size-10 text-muted-foreground/40" />
            <p className="mt-3 text-sm font-medium text-muted-foreground">Your orders will show up here</p>
            <p className="mt-1 text-xs text-muted-foreground/70">Enter your number above to look up your purchases.</p>
        </div>
    );
}

function ModeTab({
    active,
    icon: Icon,
    label,
    onClick,
}: {
    active: boolean;
    icon: typeof Phone;
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                "inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition",
                active ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
            )}
        >
            <Icon className="size-4" /> {label}
        </button>
    );
}

function NoResults({ query, mode, store }: { query: string; mode: LookupMode; store: Props["store"] }) {
    return (
        <div className="flex flex-col items-center rounded-2xl border border-dashed border-border bg-card px-6 py-14 text-center">
            <Package className="size-10 text-muted-foreground/40" />
            <p className="mt-3 text-sm font-medium text-muted-foreground">No orders for {query}</p>
            <p className="mt-1 max-w-sm text-xs text-muted-foreground/70">
                {mode === "phone"
                    ? "Make sure it's the exact number you paid with. Orders can take a moment to appear right after checkout."
                    : "Check the reference from your confirmation page — it looks like DS- followed by 10 characters."}
            </p>
            {store.whatsapp && (
                <a
                    href={`https://wa.me/${store.whatsapp.replace(/\D+/g, "")}`}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-4 text-xs font-medium text-brand hover:underline"
                >
                    Still stuck? Message {store.name}
                </a>
            )}
        </div>
    );
}
