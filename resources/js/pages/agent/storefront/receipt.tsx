import { show, track } from "@/actions/App/Http/Controllers/Storefront/StorefrontController";
import {
    StorefrontReceiptView,
    type StorefrontContact,
    type StorefrontReceiptOrder,
} from "@/components/storefront/storefront-receipt-view";

interface Props {
    agentSlug: string;
    store: StorefrontContact;
    order: StorefrontReceiptOrder;
}

export default function StorefrontReceipt({ agentSlug, store, order }: Props) {
    return (
        <StorefrontReceiptView
            store={store}
            order={order}
            buyHref={show.url({ agentSlug })}
            trackHref={track.url({ agentSlug })}
        />
    );
}
