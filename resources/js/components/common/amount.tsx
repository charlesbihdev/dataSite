import { cedis } from "@/lib/format";
import { cn } from "@/lib/utils";

/**
 * A signed money cell in the house style: green for credits, red (danger token) for debits, always
 * tabular. Debits always show a leading minus; pass `signed` to also show a leading plus on credits
 * (useful in a mixed ledger). Shared by every wallet/transaction table (ENGINEERING_PRINCIPLES §4).
 */
export function Amount({ value, signed = false, className }: { value: number; signed?: boolean; className?: string }) {
    const isDebit = value < 0;
    const prefix = isDebit ? "−" : signed ? "+" : "";

    return (
        <span className={cn("font-medium tabular-nums", isDebit ? "text-danger" : "text-success", className)}>
            {prefix}
            {cedis(Math.abs(value))}
        </span>
    );
}
