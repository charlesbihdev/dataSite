import { router } from "@inertiajs/react";
import { useState } from "react";
import { RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";

/** Status filter for wallet history: completed ledger rows vs top-ups still pending/failed. */
export const WALLET_STATUS_FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All statuses" },
    { value: "completed", label: "Completed" },
    { value: "pending", label: "Pending" },
    { value: "failed", label: "Failed" },
];

/**
 * Re-checks a pending/failed gateway top-up with the gateway and credits it if paid — the same action
 * as the admin's Reverify. `verify` is the portal's topup-verify route.
 */
export function VerifyTopupButton({ topupId, verify }: { topupId: number; verify: { url: (id: number) => string } }) {
    const [verifying, setVerifying] = useState(false);

    const run = () => {
        setVerifying(true);
        router.post(verify.url(topupId), {}, { preserveScroll: true, onFinish: () => setVerifying(false) });
    };

    return (
        <Button variant="ghost" size="sm" onClick={run} disabled={verifying}>
            <RefreshCw className={`size-3.5 ${verifying ? "animate-spin" : ""}`} />
            {verifying ? "Verifying…" : "Verify"}
        </Button>
    );
}
