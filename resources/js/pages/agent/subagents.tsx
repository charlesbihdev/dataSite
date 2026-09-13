import { Head } from "@inertiajs/react";
import { useState } from "react";
import { Check, Copy, ExternalLink, UserPlus } from "lucide-react";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { cedis } from "@/lib/format";

interface Subagent {
    id: number;
    name: string;
    username: string | null;
    phone: string;
    accountStatus: string;
    storeStatus: string;
    storeUrl: string | null;
    wallet: number;
    joined: string | null;
}

interface Props {
    referralUrl: string;
    subagents: { data: Subagent[]; links: PageLink[]; total: number };
}

export default function AgentSubagents({ referralUrl, subagents }: Props) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(referralUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const columns: Column<Subagent>[] = [
        { key: "name", header: "Name", render: (s) => <span className="font-medium">{s.name}</span> },
        { key: "username", header: "Username", render: (s) => s.username ?? <span className="text-muted-foreground">—</span> },
        { key: "phone", header: "Phone", render: (s) => <span className="font-mono text-xs">{s.phone}</span> },
        { key: "store", header: "Store", render: (s) => <StatusBadge status={s.storeStatus} /> },
        { key: "account", header: "Account", render: (s) => <StatusBadge status={s.accountStatus} /> },
        { key: "wallet", header: "Wallet", align: "right", render: (s) => <span className="tabular-nums">{cedis(s.wallet)}</span> },
        { key: "joined", header: "Joined", render: (s) => <span className="text-muted-foreground">{s.joined ?? "—"}</span> },
        {
            key: "actions",
            header: "Actions",
            align: "right",
            render: (s) =>
                s.storeUrl ? (
                    <Button asChild variant="ghost" size="sm">
                        <a href={s.storeUrl} target="_blank" rel="noreferrer">
                            Visit store <ExternalLink className="size-3.5" />
                        </a>
                    </Button>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <>
            <Head title="My Sub-Agents" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="My Sub-Agents" description="Recruit sub-agents and track everyone selling under your store." />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <UserPlus className="size-4 text-brand" /> Recruit a Sub-Agent
                        </CardTitle>
                        <CardDescription>
                            Share this link. Anyone who signs up through it becomes your sub-agent and sells under your store.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Input readOnly value={referralUrl} className="bg-muted font-mono text-xs" onFocus={(e) => e.currentTarget.select()} />
                            <Button type="button" variant="outline" onClick={copy} className="shrink-0">
                                {copied ? <Check className="size-4 text-success" /> : <Copy className="size-4" />}
                                {copied ? "Copied" : "Copy link"}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex-1 rounded-xl border border-border bg-card shadow-sm">
                    <div className="flex items-center justify-between border-b border-border p-4">
                        <h2 className="text-sm font-semibold">Your Sub-Agents</h2>
                        <span className="text-xs text-muted-foreground">{subagents.total} total</span>
                    </div>
                    <DataTable
                        columns={columns}
                        rows={subagents.data}
                        rowKey={(s) => s.id}
                        emptyMessage="No sub-agents yet. Share your link to recruit your first one."
                    />
                    {subagents.links.length > 3 && (
                        <div className="border-t border-border p-4">
                            <Pagination links={subagents.links} />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

AgentSubagents.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "My Sub-Agents", href: "/subagents" },
    ],
};
