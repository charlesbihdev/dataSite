import { ShoppingBag } from "lucide-react";
import { show, track } from "@/actions/App/Http/Controllers/Storefront/StorefrontController";
import { CheckoutDialog } from "@/components/storefront/checkout-dialog";
import { type StorefrontPkg } from "@/components/storefront/package-card";
import {
    StoreShell,
    type StorefrontStore,
} from "@/components/storefront/store-shell";
import { Button } from "@/components/ui/button";
import { type NetworkMeta } from "@/lib/networks";

interface Props {
    store: StorefrontStore;
    packages: StorefrontPkg[];
    networks: NetworkMeta[];
    agentSlug: string;
    recruitUrl: string;
}

/**
 * Domain 2 — the AGENT storefront. It is the shared shopping shell PLUS the recruitment path
 * (become a sub-agent), which is the MIDDLE rung of the ladder and lives ONLY on this tier. The
 * sealed D3 subagent storefront reuses StoreShell but passes NONE of these recruitment slots.
 */
export default function AgentStorefrontBuy({
    store,
    packages,
    networks,
    agentSlug,
    recruitUrl,
}: Props) {
    return (
        <StoreShell
            store={store}
            packages={packages}
            networks={networks}
            homeHref={show.url({ agentSlug })}
            trackHref={track.url({ agentSlug })}
            topBanner={<ResellerStrip store={store} recruitUrl={recruitUrl} />}
            promo={<RecruitSection store={store} recruitUrl={recruitUrl} />}
            renderCheckout={(pkg, close) => (
                <CheckoutDialog
                    pkg={pkg}
                    agentSlug={agentSlug}
                    networks={networks}
                    onClose={close}
                />
            )}
        />
    );
}

function ResellerStrip({
    store,
    recruitUrl,
}: {
    store: StorefrontStore;
    recruitUrl: string;
}) {
    return (
        <a
            href={recruitUrl}
            className="group block bg-brand text-brand-fg transition hover:bg-brand/90"
        >
            <div className="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-center gap-x-2 gap-y-0.5 px-4 py-2.5 text-center text-xs font-medium sm:px-6 sm:text-sm">
                <ShoppingBag className="hidden size-4 shrink-0 sm:block" />
                <span>
                    Want to sell data too? Become a reseller under {store.name}
                </span>
                <span className="font-semibold underline underline-offset-2 group-hover:no-underline">
                    Start now →
                </span>
            </div>
        </a>
    );
}

function RecruitSection({
    store,
    recruitUrl,
}: {
    store: StorefrontStore;
    recruitUrl: string;
}) {
    return (
        <section className="mt-14 overflow-hidden rounded-2xl border border-brand/20 bg-brand-subtle">
            <div className="flex flex-col items-start gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div className="flex items-start gap-4">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand text-brand-fg">
                        <ShoppingBag className="size-5" />
                    </span>
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            Want to run your own data store?
                        </h2>
                        <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                            Sign up as a sub-agent under {store.name} and start
                            selling at your own prices.
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
    );
}
