import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    BookOpen,
    Globe,
    KeyRound,
    Shield,
    Terminal,
} from "lucide-react";
import { EndpointDocCard } from "@/components/agent/api-docs/endpoint-doc-card";
import {
    CatalogNetwork,
    PackageCatalogCard,
} from "@/components/agent/api-docs/package-catalog-card";
import { PageHeader } from "@/components/common/page-header";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

interface Props {
    baseUrl: string;
    catalog: CatalogNetwork[];
    role: string;
}

export default function AgentApiDocumentation({ baseUrl, catalog }: Props) {
    return (
        <>
            <Head title="API Documentation" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="API Documentation"
                    description="Complete developer guide for automated bundle purchases, order status queries, and package pricing."
                />

                {/* Quick start protocol card */}
                <Card>
                    <CardHeader className="border-b border-border pb-4">
                        <div className="flex items-center gap-2">
                            <BookOpen className="size-5 text-brand" />
                            <CardTitle className="text-base">
                                Quick Start
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-6">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-lg border border-border p-3">
                                <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <Globe className="size-3.5" />
                                    <span>Base URL</span>
                                </div>
                                <p className="mt-1 font-mono text-xs font-medium text-foreground break-all">
                                    {baseUrl}
                                </p>
                            </div>

                            <div className="rounded-lg border border-border p-3">
                                <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <Shield className="size-3.5" />
                                    <span>Authentication</span>
                                </div>
                                <p className="mt-1 font-mono text-xs font-medium text-foreground">
                                    X-API-Key: dsk_...
                                </p>
                            </div>

                            <div className="rounded-lg border border-border p-3">
                                <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <Terminal className="size-3.5" />
                                    <span>Rate Limits</span>
                                </div>
                                <p className="mt-1 text-xs font-medium text-foreground">
                                    60 requests / minute
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/20 p-3 text-xs">
                            <span className="text-muted-foreground">
                                Authentication is required for all developer
                                endpoints. Get your API credentials from the key
                                management dashboard.
                            </span>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/api-keys" className="gap-1.5">
                                    <KeyRound className="size-3.5" />
                                    <span>Manage API Keys</span>
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Allowed packages catalog */}
                <PackageCatalogCard catalog={catalog} />

                {/* 3 Endpoints */}
                <EndpointDocCard baseUrl={baseUrl} />

                {/* Bottom CTA */}
                <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-border bg-card p-6 text-center">
                    <h3 className="font-semibold text-foreground text-sm">
                        Ready to begin integration?
                    </h3>
                    <p className="text-xs text-muted-foreground max-w-md">
                        Generate your API keys, verify your webhook endpoints,
                        and start dispatching high-speed mobile data orders.
                    </p>
                    <Button size="sm" asChild className="gap-2">
                        <Link href="/api-keys">
                            <span>Open API Keys Dashboard</span>
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

AgentApiDocumentation.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "API Documentation", href: "/api-documentation" },
    ],
};
