import { useForm } from "@inertiajs/react";
import { bulk } from "@/routes/agent/cart";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

/**
 * Paste many orders at once — one "phone size" per line. The server splits, validates, and prices
 * each line, skipping any it can't place.
 */
export function BulkOrderForm() {
    const form = useForm({ bulk_orders_text: "" });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(bulk.url(), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-1.5">
                <Label htmlFor="bulk_orders_text">Paste orders</Label>
                <Textarea
                    id="bulk_orders_text"
                    rows={6}
                    className="font-mono text-sm"
                    placeholder={"0558119187 8\n0244492125 2\n0201234567 10"}
                    value={form.data.bulk_orders_text}
                    onChange={(e) => form.setData("bulk_orders_text", e.target.value)}
                    aria-invalid={!!form.errors.bulk_orders_text}
                />
                <p className="text-xs text-muted-foreground">Format: phone number, a space, then the bundle size in GB.</p>
                {form.errors.bulk_orders_text && <p className="text-xs text-destructive">{form.errors.bulk_orders_text}</p>}
            </div>

            <Button type="submit" className="w-full" disabled={form.processing}>
                Add orders to cart
            </Button>
        </form>
    );
}
