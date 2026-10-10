import { useState } from "react";
import { AlertTriangle, CheckCircle2, Loader2, Phone, ShieldCheck, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { xsrfToken } from "@/lib/csrf";
import { cn } from "@/lib/utils";

interface Props {
    checkUrl?: string;
    className?: string;
}

interface EligibilityResult {
    ok: boolean;
    msisdn: string;
    approved: boolean;
    status: string;
    message?: string | null;
    enforced?: boolean;
}

/**
 * Quick eligibility checker widget displayed on storefronts (agent D2, subagent D3).
 * Allows walk-in customers to verify their phone number against the upstream approved
 * list before picking a bundle.
 */
export function EligibilityChecker({ checkUrl, className }: Props) {
    const [phone, setPhone] = useState("");
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<EligibilityResult | null>(null);
    const [lastChecked, setLastChecked] = useState("");

    const handleCheck = async (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        const cleanPhone = phone.trim().replace(/\D+/g, "");
        if (!cleanPhone || !checkUrl || loading) return;

        setLoading(true);
        setResult(null);

        try {
            const res = await fetch(checkUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-XSRF-TOKEN": xsrfToken(),
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ phone: cleanPhone }),
            });

            const data = await res.json();
            setResult(data);
            setLastChecked(cleanPhone);
        } catch {
            setResult({
                ok: false,
                msisdn: cleanPhone,
                approved: false,
                status: "error",
                message: "Unable to check number at the moment. Please try again.",
            });
            setLastChecked(cleanPhone);
        } finally {
            setLoading(false);
        }
    };

    if (!checkUrl) return null;

    return (
        <div
            className={cn(
                "mx-auto w-full max-w-xl rounded-2xl border border-border/80 bg-card/80 p-4 text-card-foreground shadow-xs backdrop-blur-xs sm:p-5",
                className,
            )}
        >
            <div className="flex items-center gap-2.5">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                    <ShieldCheck className="size-4.5" />
                </span>
                <div className="text-left">
                    <h3 className="text-sm font-semibold text-foreground">
                        Quick Number Eligibility Check
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        Verify if your number is approved for instant bundle delivery
                    </p>
                </div>
            </div>

            <form onSubmit={handleCheck} className="mt-3.5 flex gap-2">
                <div className="relative flex-1">
                    <Phone className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="tel"
                        inputMode="numeric"
                        placeholder="Enter phone number (e.g. 0244123456)"
                        value={phone}
                        onChange={(e) => {
                            setPhone(e.target.value);
                            if (result && e.target.value.replace(/\D+/g, "") !== lastChecked) {
                                setResult(null);
                            }
                        }}
                        className="h-10 pl-9 text-sm"
                    />
                </div>
                <Button
                    type="submit"
                    disabled={loading || phone.trim().length < 9}
                    className="h-10 px-4 text-xs font-medium sm:text-sm"
                >
                    {loading ? (
                        <>
                            <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                            Checking...
                        </>
                    ) : (
                        "Check"
                    )}
                </Button>
            </form>

            {result && (
                <div className="mt-3 animate-in fade-in slide-in-from-top-1 duration-200">
                    {result.approved || result.status === "not_enforced" ? (
                        <div className="flex items-start gap-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-700 dark:text-emerald-400">
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                            <div className="text-left leading-relaxed">
                                <span className="font-semibold text-emerald-800 dark:text-emerald-300">
                                    {result.msisdn} is eligible!
                                </span>{" "}
                                {result.status === "not_enforced"
                                    ? "Verification is not required for this network. Ready for instant delivery."
                                    : "This number is on the approved list and ready for instant delivery."}
                            </div>
                        </div>
                    ) : result.status === "blocked" ? (
                        <div className="flex items-start gap-2.5 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-400">
                            <XCircle className="mt-0.5 size-4 shrink-0 text-rose-500" />
                            <div className="text-left leading-relaxed">
                                <span className="font-semibold text-rose-800 dark:text-rose-300">
                                    {result.msisdn} is blocked.
                                </span>{" "}
                                {result.message || "This number cannot receive bundle top-ups."}
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-start gap-2.5 rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
                            <div className="text-left leading-relaxed">
                                <span className="font-semibold text-amber-900 dark:text-amber-200">
                                    {result.msisdn} is not yet on the approved list.
                                </span>{" "}
                                {result.message || "This number needs to be approved before ordering. Contact the store owner or try another number."}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
