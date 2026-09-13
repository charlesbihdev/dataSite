import { useState } from "react";
import { FileSpreadsheet, ClipboardList, Plus } from "lucide-react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { NetworkMeta } from "@/lib/networks";
import { SingleOrderForm } from "./single-order-form";
import { BulkOrderForm } from "./bulk-order-form";
import { UploadOrderForm } from "./upload-order-form";

type Tab = "single" | "excel" | "bulk";

const TABS: { key: Tab; label: string; icon: typeof Plus }[] = [
    { key: "single", label: "Single", icon: Plus },
    { key: "excel", label: "Excel", icon: FileSpreadsheet },
    { key: "bulk", label: "Bulk", icon: ClipboardList },
];

/** Place Order: three ways to fill the cart (single, Excel/CSV upload, bulk paste). */
export function PlaceOrderCard({ networks }: { networks: NetworkMeta[] }) {
    const [tab, setTab] = useState<Tab>("single");

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Place Order</CardTitle>
                <CardDescription>Add data bundles to your cart</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="flex gap-1 rounded-lg border border-border p-1" role="tablist">
                    {TABS.map(({ key, label, icon: Icon }) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={tab === key}
                            onClick={() => setTab(key)}
                            className={cn(
                                "flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition",
                                tab === key ? "bg-brand text-brand-fg" : "text-muted-foreground hover:bg-muted",
                            )}
                        >
                            <Icon className="size-4" /> {label}
                        </button>
                    ))}
                </div>

                {tab === "single" && <SingleOrderForm networks={networks} />}
                {tab === "excel" && <UploadOrderForm />}
                {tab === "bulk" && <BulkOrderForm />}
            </CardContent>
        </Card>
    );
}
