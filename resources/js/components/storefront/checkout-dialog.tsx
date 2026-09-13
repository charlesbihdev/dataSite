import { useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { Info, Loader2, Lock, Phone, ShoppingCart } from "lucide-react";
import { checkout } from "@/actions/App/Http/Controllers/Storefront/StorefrontController";
import type { StorefrontPkg } from "@/components/storefront/package-card";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { detectNetwork, normalizeMsisdn, type NetworkMeta } from "@/lib/networks";
import { cedis } from "@/lib/format";

/**
 * "Complete Your Purchase" — opens when a customer taps Buy Now on a bundle. Confirms the chosen
 * package, collects the recipient number (warning if it doesn't match the package's network), and
 * posts checkout. Keyed by package id in the parent so its state resets each time it reopens.
 */
export function CheckoutDialog({
    pkg,
    agentSlug,
    networks,
    onClose,
}: {
    pkg: StorefrontPkg | null;
    agentSlug: string;
    networks: NetworkMeta[];
    onClose: () => void;
}) {
    return (
        <Dialog open={pkg !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="overflow-hidden p-0 sm:max-w-md">
                {pkg && <CheckoutForm key={pkg.id} pkg={pkg} agentSlug={agentSlug} networks={networks} />}
            </DialogContent>
        </Dialog>
    );
}

function CheckoutForm({ pkg, agentSlug, networks }: { pkg: StorefrontPkg; agentSlug: string; networks: NetworkMeta[] }) {
    const [phone, setPhone] = useState("");
    const detected = useMemo(() => detectNetwork(phone, networks), [phone, networks]);
    const mismatch = detected !== null && detected.code !== pkg.network;

    const form = useForm<{ beneficiary_phone: string; network: string; capacity_gb: number }>({
        beneficiary_phone: "",
        network: pkg.network,
        capacity_gb: pkg.capacityGb,
    });

    const canPay = normalizeMsisdn(phone) !== "" && !mismatch && !form.processing;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform(() => ({
            beneficiary_phone: normalizeMsisdn(phone) || phone,
            network: pkg.network,
            capacity_gb: pkg.capacityGb,
        }));
        form.post(checkout.url({ agentSlug }), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit}>
            <DialogHeader className="space-y-0 border-b border-border bg-brand px-6 py-4 text-brand-fg">
                <DialogTitle className="flex items-center gap-2 text-lg">
                    <ShoppingCart className="size-5" /> Complete Your Purchase
                </DialogTitle>
                <DialogDescription className="sr-only">Confirm your bundle and enter the recipient's number to pay.</DialogDescription>
            </DialogHeader>

            <div className="space-y-5 px-6 py-6">
                <div className="flex items-start gap-3 rounded-xl border border-brand/20 bg-brand-subtle p-4">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand text-brand-fg">
                        <Info className="size-4" />
                    </span>
                    <div className="text-sm">
                        <p className="font-semibold">Selected Package:</p>
                        <p className="text-muted-foreground">
                            {pkg.networkLabel} {pkg.capacityGb}GB – {cedis(pkg.price)}
                        </p>
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="checkout-phone" className="flex items-center gap-1.5 text-sm font-semibold">
                        <Phone className="size-4" /> Enter Phone Number *
                    </Label>
                    <Input
                        id="checkout-phone"
                        inputMode="numeric"
                        autoFocus
                        placeholder="0241234567"
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        className="h-12 text-base"
                    />
                    {mismatch ? (
                        <p className="text-xs text-destructive">
                            This number looks like {detected?.label}, but the bundle is for {pkg.networkLabel}.
                        </p>
                    ) : (
                        <p className="text-xs text-muted-foreground">Enter the number that will receive the data bundle.</p>
                    )}
                </div>

                <Button type="submit" size="lg" className="w-full" disabled={!canPay}>
                    {form.processing ? <Loader2 className="size-4 animate-spin" /> : <>Pay {cedis(pkg.price)} Securely</>}
                </Button>

                <p className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                    <Lock className="size-3.5" /> Secured checkout · SSL encrypted
                </p>
            </div>
        </form>
    );
}
