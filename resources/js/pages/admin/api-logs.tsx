import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { index as apiLogsRoute } from "@/actions/App/Http/Controllers/Admin/ApiLogController";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";

type Source = "incoming" | "upstream";

interface Log {
    id: number;
    time: string | null;
    caller: string;
    network: string | null;
    success: boolean;
    httpStatus: number | null;
    error: string | null;
    endpoint: string;
    requestBody: string | null;
    responseBody: string | null;
    durationMs: number | null;
}

interface Props {
    source: Source;
    logs: { data: Log[]; links: PageLink[] };
    filters: { network: string; result: string; from: string; to: string };
    networks: string[];
    stats: { total: number; success: number; failed: number; avgMs: number };
}

const TABS: { key: Source; label: string }[] = [
    { key: "incoming", label: "Incoming API" },
    { key: "upstream", label: "Upstream errors" },
];

// Native disclosure for a request/response body — no per-row React state.
function Body({ label, content }: { label: string; content: string | null }) {
    if (!content) return <span className="text-muted-foreground">—</span>;
    return (
        <details className="group">
            <summary className="cursor-pointer list-none font-medium text-brand hover:underline">
                ▸ {label}
            </summary>
            <pre className="mt-2 max-w-xs overflow-x-auto whitespace-pre-wrap rounded-md bg-muted p-2 text-xs text-muted-foreground">
                {content}
            </pre>
        </details>
    );
}

export default function AdminApiLogs({ source, logs, filters, networks, stats }: Props) {
    const [network, setNetwork] = useState(filters.network || "all");
    const [result, setResult] = useState(filters.result || "all");
    const [from, setFrom] = useState(filters.from || "");
    const [to, setTo] = useState(filters.to || "");

    const isUpstream = source === "upstream";

    const applyFilters = () =>
        router.get(
            apiLogsRoute.url(),
            { source, network, result, from, to },
            { preserveState: true, replace: true },
        );

    const clearFilters = () => {
        setNetwork("all");
        setResult("all");
        setFrom("");
        setTo("");
        router.get(apiLogsRoute.url(), { source }, { preserveState: true, replace: true });
    };

    const switchTab = (next: Source) =>
        router.get(apiLogsRoute.url(), { source: next }, { preserveState: false });

    const columns: Column<Log>[] = [
        {
            key: "time",
            header: "Time",
            render: (l) => <span className="whitespace-nowrap text-muted-foreground">{l.time ?? "—"}</span>,
        },
        {
            key: "caller",
            header: isUpstream ? "Call" : "Reseller",
            className: "max-w-xs whitespace-normal",
            render: (l) => <span className="font-medium">{l.caller}</span>,
        },
        {
            key: "network",
            header: "Network",
            render: (l) => <span className="uppercase">{l.network ?? "—"}</span>,
        },
        {
            key: "status",
            header: "Status",
            render: (l) => <StatusBadge status={l.success ? "success" : "failed"} />,
        },
        {
            key: "httpStatus",
            header: "HTTP",
            render: (l) => <span className="font-mono">{l.httpStatus ?? "—"}</span>,
        },
        {
            key: "error",
            header: "Error",
            className: "max-w-xs whitespace-normal",
            render: (l) => <span className="text-danger">{l.error ?? "—"}</span>,
        },
        {
            key: "response",
            header: "Response",
            render: (l) => <Body label="Response body" content={l.responseBody} />,
        },
        {
            key: "duration",
            header: "Duration",
            align: "right",
            render: (l) => (
                <span className="whitespace-nowrap tabular-nums">{l.durationMs !== null ? `${l.durationMs} ms` : "—"}</span>
            ),
        },
        {
            key: "endpoint",
            header: "Endpoint & Request",
            className: "max-w-xs whitespace-normal",
            render: (l) => (
                <div className="space-y-1">
                    <span className="break-all text-xs text-muted-foreground">{l.endpoint}</span>
                    <Body label="Request body" content={l.requestBody} />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Admin — API Logs" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="API Logs"
                    description={
                        isUpstream
                            ? "Failures from our own calls out to Databundleshub — the supplier pipe, errors only."
                            : "Every developer-API request resellers make against our API to buy data and poll status."
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {TABS.map((tab) => (
                        <button
                            key={tab.key}
                            type="button"
                            onClick={() => switchTab(tab.key)}
                            className={cn(
                                "rounded-lg border px-4 py-2 text-sm font-medium transition-colors",
                                tab.key === source
                                    ? "border-brand bg-brand text-white"
                                    : "border-border bg-card text-muted-foreground hover:text-foreground",
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile label={isUpstream ? "Total Errors" : "Total Requests"} value={String(stats.total)} hint="In selected range" />
                    <StatTile label="Successful" value={String(stats.success)} hint="2xx with success" />
                    <StatTile label="Failed" value={String(stats.failed)} hint={isUpstream ? "Rejected or unreachable" : "Rejected or errored"} />
                    <StatTile label="Avg Duration" value={`${stats.avgMs} ms`} hint="Round-trip time" />
                </div>

                <form
                    className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm"
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters();
                    }}
                >
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Filters</p>
                    <p className="text-sm text-muted-foreground">
                        Default range is the last 30 days. Set “From” to search further back (up to one year).
                    </p>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="text-muted-foreground">Network</span>
                            <Select value={network} onValueChange={setNetwork}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All networks</SelectItem>
                                    {networks.map((n) => (
                                        <SelectItem key={n} value={n} className="uppercase">
                                            {n}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </label>
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="text-muted-foreground">Result</span>
                            <Select value={result} onValueChange={setResult}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="success">Success</SelectItem>
                                    <SelectItem value="failed">Failed</SelectItem>
                                </SelectContent>
                            </Select>
                        </label>
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="text-muted-foreground">From</span>
                            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-44" />
                        </label>
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="text-muted-foreground">To</span>
                            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-44" />
                        </label>
                        <Button type="submit">Apply</Button>
                        <Button type="button" variant="secondary" onClick={clearFilters}>
                            Clear
                        </Button>
                    </div>
                </form>

                <DataTable
                    columns={columns}
                    rows={logs.data}
                    rowKey={(l) => l.id}
                    emptyMessage={isUpstream ? "No upstream errors in this range." : "No API requests in this range."}
                />
                <Pagination links={logs.links} />
            </div>
        </>
    );
}
