import { Head, Link } from "@inertiajs/react";
import { ReactNode, useMemo, useState } from "react";
import {
    MessageCircle,
    PackageSearch,
    ShieldCheck,
    ShoppingBag,
    Smartphone,
    Zap,
} from "lucide-react";
import {
    PackageCard,
    type StorefrontPkg,
} from "@/components/storefront/package-card";
import {
    NetworkFilter,
    type NetworkCount,
} from "@/components/storefront/network-filter";
import { type NetworkMeta } from "@/lib/networks";
import { networkBrand } from "@/lib/network-brand";
import { cn } from "@/lib/utils";

export interface StorefrontStore {
    name: string;
    whatsapp: string | null;
    whatsappGroup: string | null;
}

/**
 * The tier-agnostic customer shopping surface shared by every storefront (agent D2, subagent D3, …):
 * header, hero, network-filtered bundle grid, and footer. It is intentionally free of any recruitment
 * or tier-specific paths — those are injected as slots (`topBanner`, `promo`) and a `renderCheckout`
 * function so each tier owns its own capabilities. NEVER put a "become a reseller" link in here.
 */
export function StoreShell({
    store,
    packages,
    networks,
    trackHref,
    renderCheckout,
    topBanner,
    promo,
}: {
    store: StorefrontStore;
    packages: StorefrontPkg[];
    networks: NetworkMeta[];
    trackHref: string;
    renderCheckout: (pkg: StorefrontPkg | null, close: () => void) => ReactNode;
    topBanner?: ReactNode;
    promo?: ReactNode;
}) {
    const [filter, setFilter] = useState("all");
    const [buying, setBuying] = useState<StorefrontPkg | null>(null);

    // Networks that actually have bundles on this store, in the server's canonical order, with counts.
    const netItems = useMemo<NetworkCount[]>(
        () =>
            networks
                .map((n) => ({
                    code: n.code,
                    label: n.label,
                    count: packages.filter((p) => p.network === n.code).length,
                }))
                .filter((n) => n.count > 0),
        [networks, packages],
    );

    const visibleNetworks = useMemo(() => {
        const codes = filter === "all" ? netItems.map((n) => n.code) : [filter];
        return codes.filter((c) => packages.some((p) => p.network === c));
    }, [filter, netItems, packages]);

    return (
        <>
            <Head title={`Buy Data · ${store.name}`} />
            <div className="min-h-screen bg-background text-foreground">
                {topBanner}
                <StoreHeader store={store} trackHref={trackHref} />

                <main className="mx-auto w-full max-w-5xl px-4 pb-20 sm:px-6">
                    <section className="py-10 text-center sm:py-14">
                        <h1 className="bg-gradient-to-br from-brand to-brand/60 bg-clip-text text-4xl font-bold tracking-tight text-transparent sm:text-5xl">
                            Buy data in seconds
                        </h1>
                        <p className="mx-auto mt-3 max-w-xl text-base text-muted-foreground">
                            Pick a bundle, enter the number to top up, and pay.
                            Simple and fast.
                        </p>
                        <div className="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5">
                                <Zap className="size-4 text-brand" /> Blazing
                                fast delivery
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Smartphone className="size-4 text-brand" /> Pay
                                with Mobile Money
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <ShieldCheck className="size-4 text-brand" />{" "}
                                Secure checkout
                            </span>
                        </div>
                        {store.whatsapp && (
                            <a
                                href={`https://wa.me/${store.whatsapp.replace(/\D+/g, "")}`}
                                target="_blank"
                                rel="noreferrer"
                                className="mx-auto mt-6 inline-flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-sm font-medium text-foreground shadow-sm transition hover:bg-muted"
                            >
                                <MessageCircle className="size-4 text-success" />
                                <span>
                                    Need help? Chat with us on{" "}
                                    <span className="font-semibold text-brand">
                                        {store.whatsapp}
                                    </span>
                                </span>
                            </a>
                        )}
                    </section>

                    <div className="space-y-6">
                        <h2 className="text-sm font-semibold">
                            Filter by network
                        </h2>
                        {packages.length === 0 ? (
                            <p className="rounded-2xl border border-border bg-card py-14 text-center text-sm text-muted-foreground">
                                This store has no bundles listed yet.
                            </p>
                        ) : (
                            <>
                                <NetworkFilter
                                    items={netItems}
                                    value={filter}
                                    onChange={setFilter}
                                />
                                <div className="space-y-8 pt-1">
                                    {visibleNetworks.map((code) => (
                                        <NetworkSection
                                            key={code}
                                            code={code}
                                            packages={packages.filter(
                                                (p) => p.network === code,
                                            )}
                                            onBuy={setBuying}
                                        />
                                    ))}
                                </div>
                            </>
                        )}
                    </div>

                    {promo}
                </main>

                <StoreFooter store={store} trackHref={trackHref} />
            </div>

            {renderCheckout(buying, () => setBuying(null))}
        </>
    );
}

function NetworkSection({
    code,
    packages,
    onBuy,
}: {
    code: string;
    packages: StorefrontPkg[];
    onBuy: (p: StorefrontPkg) => void;
}) {
    const brand = networkBrand(code);

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                    {brand.logo ? (
                        <img
                            src={brand.logo}
                            alt={brand.label}
                            className="size-8 rounded-lg object-cover"
                        />
                    ) : (
                        <span
                            className={cn(
                                "flex size-8 items-center justify-center rounded-lg text-xs font-bold",
                                brand.badge,
                            )}
                        >
                            {brand.short}
                        </span>
                    )}
                    <h3 className="text-sm font-semibold">
                        {packages[0]?.networkLabel ?? brand.label} Data Bundles
                    </h3>
                </div>
                <span className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium tabular-nums text-muted-foreground">
                    {packages.length} package{packages.length === 1 ? "" : "s"}
                </span>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {packages.map((p) => (
                    <PackageCard key={p.id} pkg={p} onBuy={() => onBuy(p)} />
                ))}
            </div>
        </div>
    );
}

function StoreHeader({
    store,
    trackHref,
}: {
    store: StorefrontStore;
    trackHref: string;
}) {
    return (
        <header className="border-b border-border bg-card/60 backdrop-blur">
            <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-3 px-4 py-3.5 sm:px-6 sm:py-4">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span className="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-brand text-brand-fg sm:flex">
                        <ShoppingBag className="size-5" />
                    </span>
                    <span className="truncate text-base font-semibold">
                        {store.name}
                    </span>
                </div>
                <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                    <Link
                        href={trackHref}
                        className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-muted hover:text-brand"
                    >
                        <PackageSearch className="size-4 shrink-0" />
                        Track order
                    </Link>
                    {store.whatsapp && (
                        <a
                            href={`https://wa.me/${store.whatsapp.replace(/\D+/g, "")}`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline"
                        >
                            <MessageCircle className="size-4 shrink-0" />
                            <span className="sr-only sm:not-sr-only">Contact</span>
                        </a>
                    )}
                </div>
            </div>
        </header>
    );
}

function StoreFooter({ store, trackHref }: { store: StorefrontStore; trackHref: string }) {
    return (
        <footer className="border-t border-border py-8 text-center text-xs text-muted-foreground">
            <p>Powered by {store.name}</p>
            <div className="mt-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                <Link href={trackHref} className="inline-flex items-center gap-1.5 text-brand hover:underline">
                    <PackageSearch className="size-3.5" /> Track your order
                </Link>
                {store.whatsappGroup && (
                    <a
                        href={store.whatsappGroup}
                        target="_blank"
                        rel="noreferrer"
                        className="text-brand hover:underline"
                    >
                        Join our WhatsApp community
                    </a>
                )}
            </div>
        </footer>
    );
}
