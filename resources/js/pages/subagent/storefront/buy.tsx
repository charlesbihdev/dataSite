import { checkout, show, track } from "@/actions/App/Http/Controllers/Storefront/SubagentStorefrontController";
import { CheckoutDialog } from "@/components/storefront/checkout-dialog";
import { type StorefrontPkg } from "@/components/storefront/package-card";
import {
    StoreShell,
    type StorefrontStore,
} from "@/components/storefront/store-shell";
import { type NetworkMeta } from "@/lib/networks";

interface Props {
    store: StorefrontStore;
    packages: StorefrontPkg[];
    networks: NetworkMeta[];
    subagentSlug: string;
}

/**
 * Domain 3 — the SUBAGENT storefront: the sealed BOTTOM of the ladder. It reuses the shared StoreShell
 * but passes NONE of the recruitment slots (no reseller strip, no "become a sub-agent" section) — a
 * customer here can only buy. Buy-only by construction (ARCHITECTURE, LADDER RULE).
 */
export default function SubagentStorefrontBuy({ store, packages, networks, subagentSlug }: Props) {
    return (
        <StoreShell
            store={store}
            packages={packages}
            networks={networks}
            homeHref={show.url({ subagentSlug })}
            trackHref={track.url({ subagentSlug })}
            renderCheckout={(pkg, close) => (
                <CheckoutDialog
                    pkg={pkg}
                    checkoutUrl={checkout.url({ subagentSlug })}
                    networks={networks}
                    onClose={close}
                />
            )}
        />
    );
}
