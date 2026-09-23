import { Boxes, Info } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export interface CatalogNetwork {
    code: string;
    api_code?: string;
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
    const apiCodes: Record<string, string> = {
        mtn: "YELLO",
        telecel: "TELECEL",
        at: "AIRTELTIGO",
    };

    return (
        <Card>
            <CardHeader className="border-b border-border pb-4">
                <div className="flex items-center gap-2">
                    <Boxes className="size-5 text-brand" />
                    <CardTitle className="text-base">
                        Allowed packages
                    </CardTitle>
                </div>
                <p className="text-xs text-muted-foreground">
                    These are the only{" "}
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-foreground font-semibold">
                        capacity
                    </code>{" "}
                    values accepted on purchase. Send whole GB (e.g.{" "}
                    <code className="font-mono text-foreground">5</code> or{" "}
                    <code className="font-mono text-foreground">"5GB"</code>).
                </p>
            </CardHeader>
            <CardContent className="space-y-4 pt-6">
                <div className="grid gap-4 lg:grid-cols-3">
                    {catalog.map((net) => {
                        const apiCode =
                            net.api_code ||
                            apiCodes[net.code.toLowerCase()] ||
                            net.code.toUpperCase();

                        return (
                            <div
                                key={net.code}
                                className="rounded-lg border border-border bg-card p-4 space-y-3"
                            >
                                <div className="flex items-center gap-2 border-b border-border pb-2">
                                    <span className="font-bold text-foreground text-sm">
                                        {net.label}
                                    </span>
                                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs uppercase text-muted-foreground font-semibold">
                                        {apiCode}
                                    </code>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {net.sizes.map((size) => (
                                        <span
                                            key={size}
                                            className="rounded border border-border bg-muted/40 px-2.5 py-1 font-mono text-xs font-semibold text-foreground"
                                        >
                                            {size} GB
                                        </span>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="flex items-center gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-xs text-foreground">
                    <Info className="size-4 text-warning shrink-0" />
                    <span>
                        Telecel numbers require at least{" "}
                        <strong className="font-semibold text-foreground">
                            10 GB
                        </strong>
                        . Other sizes return{" "}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-foreground">
                            INVALID_CAPACITY
                        </code>{" "}
                        before your wallet is debited.
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}
