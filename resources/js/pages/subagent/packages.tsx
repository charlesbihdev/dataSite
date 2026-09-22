import { Head, router, useForm } from "@inertiajs/react";
import { MoreVertical, Package as PackageIcon } from "lucide-react";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cedis } from "@/lib/format";
import { destroy, store, toggle } from "@/routes/subagent/packages";

interface Option {
    value: string;
    network: string;
    capacityGb: number;
    label: string;
    cost: number;
}
interface Pkg {
    id: number;
    network: string;
    capacityGb: number;
    cost: number;
    selling: number;
    profit: number;
    margin: number;
    isActive: boolean;
}
interface Props {
    packages: { data: Pkg[]; links: PageLink[] };
    options: Option[];
    stats: {
        total: number;
        active: number;
        avgMargin: number;
        potentialProfit: number;
    };
}

export default function SubagentPackages({ packages, options, stats }: Props) {
    const form = useForm({
        network: "",
        capacity_gb: "",
        selling_price: "",
    });
    const selected = options.find(
        (o) => o.value === `${form.data.network}:${form.data.capacity_gb}`,
    );
    const cost = selected?.cost ?? 0;
    const profit = form.data.selling_price
        ? Number(form.data.selling_price) - cost
        : 0;

    const pickPackage = (value: string) => {
        const o = options.find((x) => x.value === value);
        form.setData("network", o?.network ?? "");
        form.setData("capacity_gb", o ? String(o.capacityGb) : "");
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const columns: Column<Pkg>[] = [
        {
            key: "network",
            header: "Network",
            render: (p) => (
                <span className="font-medium uppercase">{p.network}</span>
            ),
        },
        {
            key: "package",
            header: "Package",
            render: (p) => `${p.capacityGb}GB`,
        },
        { key: "cost", header: "Cost Price", render: (p) => cedis(p.cost) },
        {
            key: "selling",
            header: "Selling Price",
            render: (p) => cedis(p.selling),
        },
        {
            key: "profit",
            header: "Profit/Sale",
            align: "right",
            render: (p) => (
                <span className="rounded-full bg-success/10 px-2 py-0.5 text-xs font-medium text-success">
                    {cedis(p.profit)}
                </span>
            ),
        },
        {
            key: "margin",
            header: "Margin",
            align: "right",
            render: (p) => <span className="tabular-nums">{p.margin}%</span>,
        },
        {
            key: "status",
            header: "Status",
            render: (p) => (
                <StatusBadge status={p.isActive ? "active" : "inactive"} />
            ),
        },
        {
            key: "actions",
            header: "",
            align: "right",
            render: (p) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon">
                            <MoreVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onClick={() =>
                                router.post(
                                    toggle(p.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {p.isActive ? "Deactivate" : "Activate"}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="text-danger focus:text-danger"
                            onClick={() =>
                                router.delete(destroy(p.id).url, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Packages" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="Packages"
                    description="Set your selling price for each data package."
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Total Packages"
                        value={String(stats.total)}
                        hint="Priced packages"
                    />
                    <StatTile
                        label="Active Packages"
                        value={String(stats.active)}
                        hint="Currently selling"
                    />
                    <StatTile
                        label="Avg Profit Margin"
                        value={`${stats.avgMargin}%`}
                        hint="Across active packages"
                    />
                    <StatTile
                        label="Total Potential Profit"
                        value={cedis(stats.potentialProfit)}
                        hint="Per sale, all active"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Add New Package Pricing
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {options.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Your agent hasn't opened any packages to you yet. Once they set sub-agent
                                prices, they'll appear here for you to price and sell.
                            </p>
                        ) : (
                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <div className="space-y-1.5">
                                        <Label>Select Package</Label>
                                        <Select
                                            value={selected?.value ?? ""}
                                            onValueChange={pickPackage}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Choose package…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.map((o) => (
                                                    <SelectItem
                                                        key={o.value}
                                                        value={o.value}
                                                    >
                                                        {o.label} -{" "}
                                                        <span className="text-muted-foreground tabular-nums">
                                                            (Cost: {cedis(o.cost)})
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.capacity_gb && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.capacity_gb}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Your Cost Price</Label>
                                        <Input
                                            readOnly
                                            value={cedis(cost)}
                                            className="bg-muted"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="selling">
                                            Your Selling Price
                                        </Label>
                                        <Input
                                            id="selling"
                                            type="number"
                                            step="0.01"
                                            min={cost}
                                            placeholder="Enter price"
                                            value={form.data.selling_price}
                                            onChange={(e) =>
                                                form.setData(
                                                    "selling_price",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.selling_price && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.selling_price}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Profit Per Sale</Label>
                                        <Input
                                            readOnly
                                            value={cedis(profit)}
                                            className="bg-success/10 text-success"
                                        />
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    disabled={!selected || form.processing}
                                >
                                    <PackageIcon className="size-4" /> Add Package
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="border-b border-border p-4">
                        <h2 className="text-sm font-semibold">My Packages</h2>
                    </div>
                    <DataTable
                        columns={columns}
                        rows={packages.data}
                        rowKey={(p) => p.id}
                        emptyMessage="No packages priced yet. Add one above."
                    />
                    {packages.links.length > 3 && (
                        <div className="border-t border-border p-4">
                            <Pagination links={packages.links} />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

SubagentPackages.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Packages", href: "/packages" },
    ],
};
