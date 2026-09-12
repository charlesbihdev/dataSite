import { Head } from "@inertiajs/react";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

export default function AgentWallet() {
    return (
        <>
            <Head title="Wallet & Top-ups" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-br from-foreground to-muted-foreground bg-clip-text text-transparent">
                        Wallet & Top-ups
                    </h1>
                    <p className="text-muted-foreground mt-2">
                        Manage your funds and view transaction history.
                    </p>
                </div>
                <Card className="border-border/50 bg-background/50 backdrop-blur-xl">
                    <CardHeader>
                        <CardTitle>Transaction History</CardTitle>
                        <CardDescription>
                            Your recent wallet activity will appear here.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="h-[300px] flex items-center justify-center text-muted-foreground">
                        Coming soon
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AgentWallet.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Wallet", href: "/wallet" },
    ],
};
