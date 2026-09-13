import { Check, Clock, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { networkBrand } from "@/lib/network-brand";
import { cedis } from "@/lib/format";
import { cn } from "@/lib/utils";

export interface StorefrontPkg {
    id: number;
    network: string;
    networkLabel: string | null;
    capacityGb: number;
    price: number;
}

/**
 * A buyable bundle as a product card: a telecom-branded header (network / size / price) over a plain
 * body with quick reassurances and a Buy Now button that opens checkout. Storefront-only styling —
 * the brand colours (lib/network-brand) are intentional here.
 */
export function PackageCard({ pkg, onBuy }: { pkg: StorefrontPkg; onBuy: () => void }) {
    const brand = networkBrand(pkg.network);

    return (
        <div className="flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div className={cn("p-5 text-center", brand.card)}>
                <p className="text-xs font-semibold tracking-wide uppercase opacity-80">{pkg.networkLabel ?? brand.label}</p>
                <p className="mt-1 text-3xl leading-none font-bold tabular-nums">{pkg.capacityGb}GB</p>
                <span className={cn("mt-3 inline-flex items-center rounded-full px-4 py-1.5 text-base font-bold tabular-nums", brand.pill)}>
                    {cedis(pkg.price)}
                </span>
            </div>
            <div className="flex flex-1 flex-col gap-3 p-4">
                <ul className="space-y-1.5 text-xs text-muted-foreground">
                    <li className="flex items-center gap-2">
                        <Check className="size-3.5 text-success" /> Instant activation
                    </li>
                    <li className="flex items-center gap-2">
                        <ShieldCheck className="size-3.5 text-success" /> Secure payment
                    </li>
                    <li className="flex items-center gap-2">
                        <Clock className="size-3.5 text-success" /> 24/7 support
                    </li>
                </ul>
                <Button type="button" onClick={onBuy} className="mt-auto w-full">
                    Buy Now
                </Button>
            </div>
        </div>
    );
}
