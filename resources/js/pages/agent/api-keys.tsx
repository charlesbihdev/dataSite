import { Head, router, useForm, usePage } from "@inertiajs/react";
import { KeyRound, Plus } from "lucide-react";
import { useState } from "react";
import { ApiDocsCard } from "@/components/agent/api-docs-card";
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
            <Head title="API Keys & Documentation" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader
                    title="API Keys & Developer Documentation"
                    description="Create and manage your credentials for automated bundle order fulfillment."
                />

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Total API Keys"
                        value={String(stats.total)}
                        hint="Generated credentials"
                    />
                    <StatTile
                        label="Active Keys"
                        value={String(stats.active)}
                        hint="Authorized for requests"
                    />
                </div>

                {/* Key Management Card */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between border-b border-border pb-4">
                        <div className="space-y-0.5">
                            <div className="flex items-center gap-2">
                                <KeyRound className="size-4 text-brand" />
                                <CardTitle className="text-base">
                                    My API Keys
                                </CardTitle>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Only you and your integrated software can use
                                these keys.
                            </p>
                        </div>
                        {!isMinting && (
                            <Button
                                type="button"
                                size="sm"
                                className="gap-1.5"
                                onClick={() => setIsMinting(true)}
                            >
                                <Plus className="size-3.5" />
                                Generate New Key
                            </Button>
                        )}
                    </CardHeader>

                    <CardContent className="space-y-4 pt-4">
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
                                        placeholder="e.g. POS Server, Mobile App, Storefront"
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
                            emptyMessage="You have not generated any API keys yet. Click 'Generate New Key' above to start integrating."
                        />
                    </CardContent>
                </Card>

                {/* API Documentation */}
                <ApiDocsCard baseUrl={baseUrl} />
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
