import { Head } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
    MessageCircle,
    ShieldCheck,
    Smartphone,
    Store,
    Zap,
} from "lucide-react";
import { CheckoutDialog } from "@/components/storefront/checkout-dialog";
import {
    PackageCard,
    type StorefrontPkg,
} from "@/components/storefront/package-card";
import {
    NetworkFilter,
    type NetworkCount,
} from "@/components/storefront/network-filter";
import { Button } from "@/components/ui/button";
import { type NetworkMeta } from "@/lib/networks";
import { networkBrand } from "@/lib/network-brand";
import { cn } from "@/lib/utils";

interface Props {
    store: {
        name: string;
        whatsapp: string | null;
        whatsappGroup: string | null;
    };
    packages: StorefrontPkg[];
    networks: NetworkMeta[];
    agentSlug: string;
    recruitUrl: string;
}

export default function StorefrontBuy({
    store,
    packages,
    networks,
    agentSlug,
    recruitUrl,
}: Props) {
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
                <StoreHeader store={store} />

                <main className="mx-auto w-full max-w-5xl px-4 pb-20 sm:px-6">
                    <section className="py-10 text-center sm:py-14">
                        <h1 className="bg-gradient-to-br from-brand to-brand/60 bg-clip-text text-4xl font-bold tracking-tight text-transparent sm:text-5xl">
                            Buy data in seconds
                        </h1>
                        <p className="mx-auto mt-3 max-w-xl text-base text-muted-foreground">
                            Pick a bundle, enter the number to top up, and pay.
                            No sign-up needed.
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

                    <section className="mt-14 overflow-hidden rounded-2xl border border-brand/20 bg-brand-subtle">
                        <div className="flex flex-col items-start gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                            <div className="flex items-start gap-4">
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand text-brand-fg">
                                    <Store className="size-5" />
                                </span>
                                <div>
                                    <h2 className="text-lg font-semibold tracking-tight">
                                        Want to run your own data store?
                                    </h2>
                                    <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                                        Sign up as a sub-agent under{" "}
                                        {store.name} and start selling at your
                                        own prices.
                                    </p>
                                </div>
                            </div>
                            <Button
                                asChild
                                size="lg"
                                className="w-full shrink-0 sm:w-auto"
                            >
                                <a href={recruitUrl}>Become a sub-agent</a>
                            </Button>
                        </div>
                    </section>
                </main>

                <StoreFooter store={store} />
            </div>

            <CheckoutDialog
                pkg={buying}
                agentSlug={agentSlug}
                networks={networks}
                onClose={() => setBuying(null)}
            />
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
                <span className="text-xs text-muted-foreground">
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

function StoreHeader({ store }: { store: Props["store"] }) {
    return (
        <header className="border-b border-border bg-card/60 backdrop-blur">
            <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
                <div className="flex items-center gap-2.5">
                    <span className="flex size-9 items-center justify-center rounded-lg bg-brand text-sm font-bold text-brand-fg">
                        {store.name.charAt(0).toUpperCase()}
                    </span>
                    <span className="text-base font-semibold">
                        {store.name}
                    </span>
                </div>
                {store.whatsapp && (
                    <a
                        href={`https://wa.me/${store.whatsapp.replace(/\D+/g, "")}`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline"
                    >
                        <MessageCircle className="size-4" /> Contact
                    </a>
                )}
            </div>
        </header>
    );
}

function StoreFooter({ store }: { store: Props["store"] }) {
    return (
        <footer className="border-t border-border py-8 text-center text-xs text-muted-foreground">
            <p>Powered by {store.name}</p>
            {store.whatsappGroup && (
                <a
                    href={store.whatsappGroup}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-1 inline-block text-brand hover:underline"
                >
                    Join our WhatsApp community
                </a>
            )}
        </footer>
    );
}
