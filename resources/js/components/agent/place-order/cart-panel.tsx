import { router, useForm } from "@inertiajs/react";
import { CreditCard, ShoppingCart, Trash2 } from "lucide-react";
import { checkout, destroy } from "@/routes/agent/cart";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cedis } from "@/lib/format";

export interface CartItem {
    id: string;
    beneficiary_phone: string;
    network: string;
    network_label: string;
    capacity_gb: number;
    bundle: string;
    cost: number;
}

/** The session cart: staged lines, running total, per-line remove, and checkout. */
export function CartPanel({ items, total }: { items: CartItem[]; total: number }) {
    const checkoutForm = useForm({});

    const remove = (id: string) => router.delete(destroy.url(id), { preserveScroll: true });
    const pay = () => checkoutForm.post(checkout.url(), { preserveScroll: true });

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle className="flex items-center gap-2 text-base">
                    <ShoppingCart className="size-4 text-muted-foreground" /> Cart
                </CardTitle>
                {items.length > 0 && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        {items.length} item{items.length === 1 ? "" : "s"}
                    </span>
                )}
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-10 text-center">
                        <ShoppingCart className="size-8 text-muted-foreground/50" />
                        <p className="text-sm font-medium">Your cart is empty</p>
                        <p className="text-xs text-muted-foreground">Add bundles using the order form.</p>
                    </div>
                ) : (
                    <>
                        <ul className="divide-y divide-border">
                            {items.map((item) => (
                                <li key={item.id} className="flex items-center justify-between gap-3 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate font-mono text-sm font-medium">{item.beneficiary_phone}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.network_label} · {item.bundle}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm font-semibold tabular-nums">{cedis(item.cost)}</span>
                                        <button
                                            type="button"
                                            onClick={() => remove(item.id)}
                                            className="text-muted-foreground transition hover:text-destructive"
                                            aria-label={`Remove ${item.beneficiary_phone}`}
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-2 flex items-center justify-between border-t border-border py-3">
                            <span className="text-sm text-muted-foreground">Total</span>
                            <span className="text-lg font-bold tabular-nums">{cedis(total)}</span>
                        </div>

                        <Button type="button" className="w-full" onClick={pay} disabled={checkoutForm.processing}>
                            <CreditCard className="size-4" />
                            {checkoutForm.processing ? "Processing…" : `Make payment (${cedis(total)})`}
                        </Button>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
