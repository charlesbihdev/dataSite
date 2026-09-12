import { Head } from "@inertiajs/react";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

export default function AgentSubagents() {
    return (
        <>
            <Head title="My Subagents" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-br from-foreground to-muted-foreground bg-clip-text text-transparent">
                        My Subagents
                    </h1>
                    <p className="text-muted-foreground mt-2">
                        View and manage subagents you have recruited.
                    </p>
                </div>
                <Card className="border-border/50 bg-background/50 backdrop-blur-xl">
                    <CardHeader>
                        <CardTitle>Subagents List</CardTitle>
                        <CardDescription>
                            See how much commission you've earned from each
                            subagent.
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

AgentSubagents.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Subagents", href: "/subagents" },
    ],
};
