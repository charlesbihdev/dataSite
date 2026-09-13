import { useForm } from "@inertiajs/react";
import { store } from "@/routes/agent/cart";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { detectNetwork, NetworkMeta } from "@/lib/networks";

/**
 * Add one bundle to the cart. The network is detected live from the phone prefix (server-provided
 * table) and drives the size suggestions; the server re-validates and prices on submit.
 */
export function SingleOrderForm({ networks }: { networks: NetworkMeta[] }) {
    const form = useForm({ beneficiary_phone: "", bundle_size: "" });
    const detected = detectNetwork(form.data.beneficiary_phone, networks);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(store.url(), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-1.5">
                <Label htmlFor="beneficiary_phone">Phone number</Label>
                <Input
                    id="beneficiary_phone"
                    inputMode="tel"
                    maxLength={10}
                    placeholder="0551234567"
                    value={form.data.beneficiary_phone}
                    onChange={(e) => form.setData("beneficiary_phone", e.target.value.replace(/\D/g, ""))}
                    aria-invalid={!!form.errors.beneficiary_phone}
                />
                <p className="text-xs text-muted-foreground">
                    {detected ? (
                        <span className="font-medium text-brand">{detected.label} detected</span>
                    ) : form.data.beneficiary_phone.length >= 3 ? (
                        <span className="text-destructive">Unrecognized network prefix</span>
                    ) : (
                        "MTN, Telecel, or AirtelTigo — 10 digits starting with 0."
                    )}
                </p>
                {form.errors.beneficiary_phone && (
                    <p className="text-xs text-destructive">{form.errors.beneficiary_phone}</p>
                )}
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="bundle_size">Bundle size (GB)</Label>
                <Input
                    id="bundle_size"
                    type="number"
                    min={1}
                    step={1}
                    list="agent_bundle_sizes"
                    placeholder="5"
                    value={form.data.bundle_size}
                    onChange={(e) => form.setData("bundle_size", e.target.value)}
                    aria-invalid={!!form.errors.bundle_size}
                />
                <datalist id="agent_bundle_sizes">
                    {(detected?.sizes ?? []).map((size) => (
                        <option key={size} value={size}>{size} GB</option>
                    ))}
                </datalist>
                {detected?.code === "telecel" && (
                    <p className="text-xs text-muted-foreground">Telecel requires 10 GB minimum.</p>
                )}
                {form.errors.bundle_size && <p className="text-xs text-destructive">{form.errors.bundle_size}</p>}
            </div>

            <Button type="submit" className="w-full" disabled={form.processing}>
                Add to cart
            </Button>
        </form>
    );
}
