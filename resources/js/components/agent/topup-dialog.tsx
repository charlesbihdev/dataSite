import { useForm } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cedis } from "@/lib/format";
import { topup } from "@/routes/agent";

const QUICK_AMOUNTS = [50, 100, 200, 500];

/**
 * Opens a payment-gateway checkout to fund the agent's wallet. The amount is the only thing sent —
 * the server owns pricing, verification, and the actual credit. On submit the server hands the
 * browser off to the gateway's hosted page (Inertia location redirect).
 */
export function TopupDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({ amount: "" });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(topup.url(), { preserveScroll: true });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="bg-brand text-brand-fg hover:bg-brand-hover">
                    <Plus className="size-4" /> Top up wallet
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Top up wallet</DialogTitle>
                        <DialogDescription>
                            Enter the amount to add to your wallet. You'll be redirected to the secure
                            payment page to complete it.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="topup-amount">Amount (GHS)</Label>
                            <Input
                                id="topup-amount"
                                type="number"
                                min={1}
                                step="0.01"
                                inputMode="decimal"
                                placeholder="0.00"
                                value={form.data.amount}
                                onChange={(e) => form.setData("amount", e.target.value)}
                                autoFocus
                            />
                            {form.errors.amount && <p className="text-sm text-danger">{form.errors.amount}</p>}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {QUICK_AMOUNTS.map((amount) => (
                                <Button
                                    key={amount}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => form.setData("amount", String(amount))}
                                >
                                    {cedis(amount)}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing} className="bg-brand text-brand-fg hover:bg-brand-hover">
                            {form.processing ? "Redirecting…" : "Continue to payment"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
