import { Boxes, Info } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export interface CatalogNetwork {
    code: string;
    label: string;
    prefixes: string[];
    sizes: number[];
    minGb: number;
    maxGb: number;
    samples?: {
        capacity: number;
        price: string;
        pricePerGB: string;
    }[];
}

interface PackageCatalogCardProps {
    catalog: CatalogNetwork[];
}

export function PackageCatalogCard({ catalog }: PackageCatalogCardProps) {
    return (
        <Card>
            <CardHeader className="border-b border-border pb-4">
                <div className="flex items-center gap-2">
                    <Boxes className="size-5 text-brand" />
                    <CardTitle className="text-base">
                        Allowed Packages Catalog
                    </CardTitle>
                </div>
                <p className="text-xs text-muted-foreground">
                    Only the whole GB capacities listed below are accepted when
                    placing orders.
                </p>
            </CardHeader>
            <CardContent className="space-y-4 pt-6">
                <div className="grid gap-4 lg:grid-cols-3">
                    {catalog.map((net) => (
                        <div
                            key={net.code}
                            className="rounded-lg border border-border bg-card p-4 space-y-3"
                        >
                            <div className="flex items-center justify-between border-b border-border pb-2">
                                <span className="font-semibold text-foreground text-sm">
                                    {net.label}
                                </span>
                                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs uppercase text-muted-foreground">
                                    {net.code}
                                </code>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {net.sizes.map((size) => (
                                    <span
                                        key={size}
                                        className="rounded border border-border bg-muted/30 px-2 py-0.5 font-mono text-xs text-foreground"
                                    >
                                        {size} GB
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/20 p-3 text-xs text-muted-foreground">
                    <Info className="size-4 text-brand shrink-0" />
                    <span>
                        <strong className="text-foreground">
                            Telecel restriction:
                        </strong>{" "}
                        Telecel orders require a minimum capacity of 10 GB.
                        Other sizes will be rejected with{" "}
                        <code className="font-mono text-foreground">
                            INVALID_CAPACITY
                        </code>{" "}
                        before debit.
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}
