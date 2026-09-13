import { Head, router, usePage } from "@inertiajs/react";
import { Link as LinkIcon, ShoppingBag, TrendingUp, Users, Wallet } from "lucide-react";
import { PlaceOrderCard } from "@/components/agent/place-order/place-order-card";
import { CartItem, CartPanel } from "@/components/agent/place-order/cart-panel";
import { Column, DataTable } from "@/components/common/data-table";
import { DateRangePicker, DateRangeValue } from "@/components/common/date-range-picker";
import { StatusBadge } from "@/components/common/status-badge";
import { NetworkMeta } from "@/lib/networks";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { cedis } from "@/lib/format";
import { dashboard } from "@/routes/agent";

interface Filters {
    range: string;
    from: string | null;
    to: string | null;
}

interface Stats {
    ordersCount: number;
    revenue: number;
    walletBalance: number;
    subagentsCount: number;
}

interface RecentOrder {
    id: number;
    reference: string;
    network: string;
    capacityGb: number;
    customerPrice: number;
    status: string;
    createdAt: string | null;
}

const orderColumns: Column<RecentOrder>[] = [
    { key: "reference", header: "Reference", render: (o) => <span className="font-mono text-xs">{o.reference}</span> },
    { key: "bundle", header: "Bundle", render: (o) => <span className="uppercase">{o.network} {o.capacityGb}GB</span> },
    { key: "customerPrice", header: "Amount", align: "right", render: (o) => cedis(o.customerPrice) },
    { key: "status", header: "Status", render: (o) => <StatusBadge status={o.status} /> },
    { key: "createdAt", header: "When", render: (o) => <span className="text-muted-foreground">{o.createdAt ?? "—"}</span> },
];

export default function Dashboard({
    filters,
    stats,
    recentOrders,
    cart,
    cartTotal,
    networks,
}: {
    filters: Filters;
    stats: Stats;
    recentOrders: RecentOrder[];
    cart: CartItem[];
    cartTotal: number;
    networks: NetworkMeta[];
}) {
    const { auth } = usePage().props as any;
    const user = auth.user;
    const storeUrl = `${window.location.protocol}//${window.location.host}/${user?.slug}`;

    // The date filter drives a server round-trip: Inertia re-requests this same
    // route with the range as query params, the controller recomputes the
    // range-scoped stats, and the cards + table re-render. preserveState keeps
    // the page mounted; preserveScroll avoids jumping to the top.
    const applyRange = (next: DateRangeValue) =>
        router.get(
            dashboard().url,
            { range: next.range, from: next.from ?? null, to: next.to ?? null },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Agent Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-br from-foreground to-muted-foreground bg-clip-text text-transparent">
                            Welcome back, {user?.name?.split(" ")[0]}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            Here's what's happening with your storefront.
                        </p>
                    </div>
                    <DateRangePicker
                        value={{ range: filters.range, from: filters.from, to: filters.to }}
                        onChange={applyRange}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Wallet Balance
                            </CardTitle>
                            <Wallet className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {cedis(stats.walletBalance)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Available for purchases
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                className="w-full mt-4 bg-background/50"
                            >
                                Top Up Wallet
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Your Storefront
                            </CardTitle>
                            <LinkIcon className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-sm font-medium truncate mt-2">
                                <a
                                    href={storeUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="hover:underline text-primary"
                                >
                                    {window.location.host}/{user?.slug}
                                </a>
                            </div>
                            <p className="text-xs text-muted-foreground mt-2">
                                Share this link with customers
                            </p>
                            <Button
                                variant="secondary"
                                size="sm"
                                className="w-full mt-3"
                                onClick={() =>
                                    navigator.clipboard.writeText(storeUrl)
                                }
                            >
                                Copy Link
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Orders
                            </CardTitle>
                            <ShoppingBag className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.ordersCount}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Bundles sold in range
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Revenue
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{cedis(stats.revenue)}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                From delivered orders in range
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <PlaceOrderCard networks={networks} />
                    <CartPanel items={cart} total={cartTotal} />
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7 mt-4">
                    <Card className="col-span-4 border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Recent Orders</CardTitle>
                                <CardDescription>
                                    Your storefront activity for the selected range.
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                columns={orderColumns}
                                rows={recentOrders}
                                rowKey={(o) => o.id}
                                emptyMessage="No orders in this period."
                            />
                        </CardContent>
                    </Card>

                    <Card className="col-span-3 border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Subagents
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.subagentsCount}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Active subagents recruited
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: "Dashboard",
            href: dashboard(),
        },
    ],
};
