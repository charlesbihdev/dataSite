import { router } from "@inertiajs/react";
import { useState } from "react";
import { Clock, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cedis } from "@/lib/format";

export interface PendingTopup {
    id: number;
    reference: string;
    gateway: string;
    amount: number;
    chargedAmount: number;
    date: string | null;
}

/**
 * Gateway top-ups still awaiting confirmation, each with a Verify Payment button for payers whose
 * balance didn't update. `verify` is the portal's topup-verify route.
 */
export function PendingTopups({ topups, verify }: { topups: PendingTopup[]; verify: { url: (id: number) => string } }) {
    const [verifyingId, setVerifyingId] = useState<number | null>(null);

    if (topups.length === 0) {
        return null;
    }

    const handleVerify = (id: number) => {
        setVerifyingId(id);
        router.post(verify.url(id), {}, { preserveScroll: true, onFinish: () => setVerifyingId(null) });
    };

    return (
        <div className="space-y-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
            <div className="flex items-center gap-2">
                <Clock className="size-4.5 text-amber-600 dark:text-amber-400" />
                <h3 className="text-sm font-semibold text-foreground">Pending Wallet Top-ups ({topups.length})</h3>
            </div>
            <p className="text-xs text-muted-foreground">
                These top-ups were started at the payment gateway. If you completed payment but your balance hasn't updated, click{" "}
                <strong>Verify Payment</strong> below to check and credit your wallet.
            </p>
            <div className="divide-y divide-border/60 rounded-lg border border-border/60 bg-card">
                {topups.map((topup) => {
                    const isVerifying = verifyingId === topup.id;
                    return (
                        <div key={topup.id} className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-col">
                                <div className="flex items-center gap-2">
                                    <span className="font-mono text-xs font-semibold text-foreground">{topup.reference}</span>
                                    <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase text-muted-foreground">
                                        {topup.gateway}
                                    </span>
                                </div>
                                <span className="text-xs text-muted-foreground">Initiated: {topup.date ?? "—"}</span>
                            </div>

                            <div className="flex items-center justify-between gap-4 sm:justify-end">
                                <div className="text-right">
                                    <span className="text-sm font-bold text-foreground">{cedis(topup.amount)}</span>
                                    <span className="block text-[11px] text-muted-foreground">Charged: {cedis(topup.chargedAmount)}</span>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-8 border-amber-500/30 text-xs font-medium text-amber-700 hover:bg-amber-500/15 dark:text-amber-300"
                                    disabled={isVerifying}
                                    onClick={() => handleVerify(topup.id)}
                                >
                                    <RefreshCw className={`mr-1.5 size-3.5 ${isVerifying ? "animate-spin" : ""}`} />
                                    {isVerifying ? "Verifying..." : "Verify Payment"}
                                </Button>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
