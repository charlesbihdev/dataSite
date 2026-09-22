import { show } from "@/actions/App/Http/Controllers/Storefront/SubagentStorefrontController";
import type { StorefrontContact } from "@/components/storefront/storefront-receipt-view";
import {
    StorefrontTrackView,
    type TrackedOrder,
} from "@/components/storefront/storefront-track-view";

interface Props {
    subagentSlug: string;
    store: StorefrontContact;
    by: "phone" | "reference";
    phone: string;
    reference: string;
    orders: TrackedOrder[];
}

export default function SubagentStorefrontTrack({ subagentSlug, store, by, phone, reference, orders }: Props) {
    return (
        <StorefrontTrackView
            store={store}
            by={by}
            phone={phone}
            reference={reference}
            orders={orders}
            homeHref={show.url({ subagentSlug })}
        />
    );
}
