/**
 * Telecom brand accents for the PUBLIC STOREFRONT ONLY. Ghanaian networks are recognised at a glance
 * by colour — MTN yellow, Telecel red, AirtelTigo navy — so the customer-facing shop leans on them for
 * scannability. This file is the single source of truth; the internal admin/agent portal stays on the
 * blue/white app tokens (ENGINEERING_PRINCIPLES). The hex here is treated like a logo, not app theme.
 *
 * NOTE: keep the class strings as complete literals so Tailwind's JIT detects the arbitrary colours.
 */
export interface NetworkBrand {
    label: string;
    short: string; // badge text fallback when logo is missing
    logo: string | null; // path to the real telecom logo (relative to public/)
    badge: string; // small square logo-style badge
    card: string; // full bundle-card surface
    pill: string; // price pill inside a card
}

const BRANDS: Record<string, NetworkBrand> = {
    mtn: {
        label: "MTN",
        short: "MTN",
        logo: "/images/networks/mtn.png",
        badge: "bg-[#FFCC08] text-black",
        card: "bg-[#FFCC08] text-black",
        pill: "bg-black/10 text-black",
    },
    telecel: {
        label: "Telecel",
        short: "t",
        logo: "/images/networks/telecel.png",
        badge: "bg-[#E4032E] text-white",
        card: "bg-[#E4032E] text-white",
        pill: "bg-white/20 text-white",
    },
    at: {
        label: "AirtelTigo",
        short: "AT",
        logo: "/images/networks/at.png",
        badge: "bg-[#122A6B] text-white",
        card: "bg-[#122A6B] text-white",
        pill: "bg-white/15 text-white",
    },
};

/** Neutral fallback on our own brand token for any unmapped network code. */
const FALLBACK: NetworkBrand = {
    label: "Network",
    short: "•",
    logo: null,
    badge: "bg-brand text-brand-fg",
    card: "bg-brand text-brand-fg",
    pill: "bg-white/15 text-white",
};

export function networkBrand(code: string): NetworkBrand {
    return BRANDS[code] ?? FALLBACK;
}
