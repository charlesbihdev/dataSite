import { networkBrand } from "@/lib/network-brand";
import { cn } from "@/lib/utils";

export interface NetworkCount {
    code: string;
    label: string;
    count: number;
}

/**
 * "Filter by network" chips for the storefront. Each network chip carries a telecom-coloured badge so
 * customers spot their network instantly; the active chip uses our app brand to stay on-identity.
 */
export function NetworkFilter({
    items,
    value,
    onChange,
}: {
    items: NetworkCount[];
    value: string;
    onChange: (v: string) => void;
}) {
    const total = items.reduce((sum, i) => sum + i.count, 0);

    return (
        <div className="flex flex-wrap gap-2">
            <Chip
                active={value === "all"}
                label="All Networks"
                count={total}
                onClick={() => onChange("all")}
            />
            {items.map((i) => (
                <Chip
                    key={i.code}
                    code={i.code}
                    active={value === i.code}
                    label={i.label}
                    count={i.count}
                    onClick={() => onChange(i.code)}
                />
            ))}
        </div>
    );
}

function Chip({
    code,
    active,
    label,
    count,
    onClick,
}: {
    code?: string;
    active: boolean;
    label: string;
    count: number;
    onClick: () => void;
}) {
    const brand = code ? networkBrand(code) : null;

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition",
                active
                    ? "border-transparent bg-brand text-brand-fg shadow-sm"
                    : "border-border bg-card text-foreground hover:bg-muted",
            )}
        >
            {brand &&
                (brand.logo ? (
                    <img
                        src={brand.logo}
                        alt={brand.label}
                        className="size-5 rounded object-cover"
                    />
                ) : (
                    <span
                        className={cn(
                            "flex size-5 items-center justify-center rounded text-[10px] font-bold",
                            brand.badge,
                        )}
                    >
                        {brand.short}
                    </span>
                ))}
            <span>{label}</span>
            <span
                className={cn(
                    "inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs tabular-nums",
                    active
                        ? "bg-brand-fg/20 text-brand-fg"
                        : "bg-muted text-muted-foreground",
                )}
            >
                {count}
            </span>
        </button>
    );
}
