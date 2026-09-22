import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    BookOpen,
    ExternalLink,
    KeyRound,
    Plus,
    ShieldAlert,
} from "lucide-react";
import { useState } from "react";
import { ApiKeyItem, ApiKeyList } from "@/components/common/api-key-list";
import { ApiKeyReveal } from "@/components/common/api-key-reveal";
import { PageHeader } from "@/components/common/page-header";
import { StatTile } from "@/components/common/stat-tile";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { destroy, store, toggle } from "@/routes/agent/api-keys";

interface Props {
    keys: ApiKeyItem[];
    stats: {
        total: number;
        active: number;
    };
    baseUrl: string;
}

interface PageProps {
    [key: string]: unknown;
    flash?: {
        rawApiKey?: {
            rawKey: string;
            name: string;
            prefix: string;
        } | null;
    };
}

export default function AgentApiKeys({ keys, stats, baseUrl }: Props) {
    const { props } = usePage<PageProps>();
    const rawFlash = props.flash?.rawApiKey;
    const [isMinting, setIsMinting] = useState(false);
    const hasReachedLimit = stats.active >= 3;

    const form = useForm({
        name: "Default API Key",
    });

    const handleMint = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setIsMinting(false);
                form.reset();
            },
        });
    };

    const handleToggle = (keyId: number) => {
        router.post(toggle(keyId).url, {}, { preserveScroll: true });
    };

    const handleRevoke = (keyId: number) => {
        router.delete(destroy(keyId).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="API Keys" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="API Key Management"
                    description="Create and manage your developer credentials for automated data bundle orders."
                />

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Total Keys"
                        value={String(stats.total)}
                        hint="All generated credentials"
                    />
                    <StatTile
                        label="Active Keys"
                        value={`${stats.active} / 3`}
                        hint="Maximum 3 active keys"
                    />
                </div>

                {/* Key Management Card */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between border-b border-border pb-4">
                        <div className="space-y-0.5">
                            <div className="flex items-center gap-2">
                                <KeyRound className="size-4 text-brand" />
                                <CardTitle className="text-base">
                                    Your API Keys
                                </CardTitle>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Only your authorized applications should use
                                these credentials.
                            </p>
                        </div>
                        {!isMinting && !hasReachedLimit && (
                            <Button
                                type="button"
                                size="sm"
                                className="gap-1.5"
                                onClick={() => setIsMinting(true)}
                            >
                                <Plus className="size-3.5" />
                                Generate API Key
                            </Button>
                        )}
                    </CardHeader>

                    <CardContent className="space-y-4 pt-4">
                        {/* Maximum limit notice */}
                        {hasReachedLimit ? (
                            <div className="flex items-center gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-xs text-foreground">
                                <ShieldAlert className="size-4 text-warning shrink-0" />
                                <span>
                                    You have reached the maximum of 3 active API
                                    keys. Suspend or revoke an existing key to
                                    generate a new one.
                                </span>
                            </div>
                        ) : null}

                        {/* Raw key reveal banner */}
                        {rawFlash?.rawKey ? (
                            <ApiKeyReveal rawKey={rawFlash.rawKey} />
                        ) : null}

                        {/* Inline mint form */}
                        {isMinting ? (
                            <form
                                onSubmit={handleMint}
                                className="rounded-lg border border-border bg-muted/30 p-4 space-y-3"
                            >
                                <div className="space-y-1.5">
                                    <Label htmlFor="key-name">Key Label</Label>
                                    <Input
                                        id="key-name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData("name", e.target.value)
                                        }
                                        placeholder="e.g. POS Terminal, Web Storefront, Mobile App"
                                        autoFocus
                                        className="max-w-md"
                                    />
                                    {form.errors.name ? (
                                        <p className="text-xs text-danger">
                                            {form.errors.name}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setIsMinting(false);
                                            form.reset();
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={form.processing}
                                    >
                                        {form.processing
                                            ? "Generating…"
                                            : "Create Key"}
                                    </Button>
                                </div>
                            </form>
                        ) : null}

                        {/* Key list */}
                        <ApiKeyList
                            keys={keys}
                            onToggle={handleToggle}
                            onRevoke={handleRevoke}
                            emptyMessage="No API keys generated yet. Click 'Generate API Key' to create your first credential."
                        />
                    </CardContent>
                </Card>

                {/* API Quick Reference Card */}
                <Card className="border-border">
                    <CardHeader className="border-b border-border pb-3">
                        <div className="flex items-center gap-2">
                            <BookOpen className="size-4 text-brand" />
                            <CardTitle className="text-sm font-semibold">
                                Developer API Integration
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4 text-xs text-muted-foreground">
                        <p>
                            Use your API key to automate data bundle purchases
                            and query real-time order status directly through
                            standard HTTP endpoints.
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-border bg-muted/20 p-3">
                                <span className="font-semibold uppercase tracking-wider text-foreground">
                                    Base Endpoint
                                </span>
                                <p className="mt-1 font-mono text-foreground break-all">
                                    {baseUrl}
                                </p>
                            </div>
                            <div className="rounded-lg border border-border bg-muted/20 p-3">
                                <span className="font-semibold uppercase tracking-wider text-foreground">
                                    Authentication Header
                                </span>
                                <p className="mt-1 font-mono text-foreground">
                                    X-API-Key: dsk_...
                                </p>
                            </div>
                        </div>
                        <div className="pt-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href="/api-documentation"
                                    className="gap-2 text-foreground hover:text-brand"
                                >
                                    <span>View Full API Documentation</span>
                                    <ExternalLink className="size-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AgentApiKeys.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "API Keys", href: "/api-keys" },
    ],
};
