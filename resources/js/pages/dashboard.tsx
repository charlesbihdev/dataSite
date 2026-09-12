import { Head, usePage } from "@inertiajs/react";
import { dashboard } from "@/routes/agent";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Wallet, Link as LinkIcon, ShoppingBag, Users } from "lucide-react";
import { Button } from "@/components/ui/button";

export default function Dashboard() {
    const { auth } = usePage().props as any;
    const user = auth.user;
    const walletBalance = parseFloat(user?.wallet?.balance || "0");
    const storeUrl = `${window.location.protocol}//${window.location.host}/${user?.slug}`;

    return (
        <>
            <Head title="Agent Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-br from-foreground to-muted-foreground bg-clip-text text-transparent">
                        Welcome back, {user?.name?.split(" ")[0]}
                    </h1>
                    <p className="text-muted-foreground mt-2">
                        Here's what's happening with your storefront today.
                    </p>
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
                                ₵ {walletBalance.toFixed(2)}
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
                                Total Orders
                            </CardTitle>
                            <ShoppingBag className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">0</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Lifetime bundles sold
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Subagents
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">0</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Active subagents recruited
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Placeholder for recent orders or charts */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7 mt-4">
                    <Card className="col-span-4 border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader>
                            <CardTitle>Recent Sales</CardTitle>
                            <CardDescription>
                                Your storefront activity over the last 7 days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pl-2 h-[300px] flex items-center justify-center text-muted-foreground">
                            No sales data available yet.
                        </CardContent>
                    </Card>

                    <Card className="col-span-3 border-border/50 bg-background/50 backdrop-blur-xl">
                        <CardHeader>
                            <CardTitle>Recent Orders</CardTitle>
                            <CardDescription>
                                The latest orders placed by your customers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="h-[300px] flex items-center justify-center text-muted-foreground">
                            No orders found.
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
