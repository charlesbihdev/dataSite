import { show, track } from "@/actions/App/Http/Controllers/Storefront/SubagentStorefrontController";
import {
    StorefrontReceiptView,
    type StorefrontContact,
    type StorefrontReceiptOrder,
} from "@/components/storefront/storefront-receipt-view";

interface Props {
    subagentSlug: string;
    store: StorefrontContact;
    order: StorefrontReceiptOrder;
}

export default function SubagentStorefrontReceipt({ subagentSlug, store, order }: Props) {
    return (
        <StorefrontReceiptView
            store={store}
            order={order}
            buyHref={show.url({ subagentSlug })}
            trackHref={track.url({ subagentSlug })}
        />
    );
}
