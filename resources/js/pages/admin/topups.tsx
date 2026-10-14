import { Head, router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import {
    AlertCircle,
    ArrowDownToLine,
    Check,
    CheckCircle2,
    Copy,
    Download,
    Eye,
    RefreshCw,
    Search,
    XCircle,
} from "lucide-react";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cedis } from "@/lib/format";

interface Topup {
    id: number;
    reference: string;
    gatewayReference: string | null;
    gateway: string;
    gatewayLabel: string;
    amount: number;
    chargedAmount: number;
    currency: string;
    status: string;
    agentName: string;
    agentEmail: string;
    agentPhone: string;
    completedAt: string | null;
    createdAt: string;
}

interface Props {
    topups: { data: Topup[]; links: PageLink[] };
    stats: {
        total_volume: number;
        completed_count: number;
        pending_count: number;
        failed_count: number;
        total_count: number;
    };
    filters: {
        status: string;
        gateway: string;
        q: string;
        date_from: string;
        date_to: string;
    };
}

const STATUS_OPTIONS = [
    { value: "all", label: "All Statuses" },
    { value: "pending", label: "Pending Payment" },
    { value: "success", label: "Success (Credited)" },
    { value: "failed", label: "Failed" },
];

const GATEWAY_OPTIONS = [
    { value: "all", label: "All Gateways" },
    { value: "paystack", label: "Paystack" },
    { value: "moolre", label: "Moolre" },
];

export default function AdminTopups({ topups, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
    const [verifyingId, setVerifyingId] = useState<number | null>(null);
    const [viewingTopup, setViewingTopup] = useState<Topup | null>(null);
    const [copiedRef, setCopiedRef] = useState<string | null>(null);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (patch: Record<string, string | null>) => {
        router.get(
            "/admin/topups",
            {
                status: filters.status,
                gateway: filters.gateway,
                q: search,
                date_from: filters.date_from,
                date_to: filters.date_to,
                ...patch,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => setSearch(filters.q ?? ""), [filters.q]);

    const onSearchChange = (val: string) => {
        setSearch(val);
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => apply({ q: val }), 400);
    };

    const copyText = (text: string) => {
        navigator.clipboard.writeText(text).then(() => {
            setCopiedRef(text);
            setTimeout(() => setCopiedRef(null), 1500);
        });
    };

    const handleVerify = (id: number) => {
        setVerifyingId(id);
        router.post(
            `/admin/topups/${id}/verify-payment`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setVerifyingId(null),
            },
        );
    };

    const exportCsv = (onlySelected = false) => {
        const params = new URLSearchParams();
        if (onlySelected && selectedIds.length > 0) {
            params.set("ids", selectedIds.join(","));
        } else {
            if (filters.status && filters.status !== "all") params.set("status", filters.status);
            if (filters.gateway && filters.gateway !== "all") params.set("gateway", filters.gateway);
            if (search) params.set("q", search);
            if (filters.date_from) params.set("date_from", filters.date_from);
            if (filters.date_to) params.set("date_to", filters.date_to);
        }

        window.location.href = `/admin/topups/export?${params.toString()}`;
    };

    const columns: Column<Topup>[] = [
        {
            key: "reference",
            header: "Reference",
            render: (t) => (
                <div className="flex flex-col">
                    <div className="flex items-center gap-1.5 font-mono text-xs font-semibold">
                        <span>{t.reference}</span>
                        <button
                            type="button"
                            onClick={() => copyText(t.reference)}
                            className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Copy reference"
                        >
                            {copiedRef === t.reference ? (
                                <Check className="size-3 text-success" />
                            ) : (
                                <Copy className="size-3" />
                            )}
                        </button>
                    </div>
                    {t.gatewayReference && (
                        <span className="font-mono text-[11px] text-muted-foreground">
                            GW: {t.gatewayReference}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: "agent",
            header: "Agent",
            render: (t) => (
                <div className="flex flex-col">
                    <span className="font-medium text-foreground">{t.agentName}</span>
                    <span className="text-xs text-muted-foreground">{t.agentPhone}</span>
                </div>
            ),
        },
        {
            key: "amount",
            header: "Amount",
            align: "right",
            render: (t) => (
                <div className="flex flex-col items-end">
                    <span className="font-semibold text-foreground">{cedis(t.amount)}</span>
                    <span className="text-[11px] text-muted-foreground">
                        Charged: {cedis(t.chargedAmount)}
                    </span>
                </div>
            ),
        },
        {
            key: "gateway",
            header: "Gateway",
            render: (t) => (
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-semibold uppercase text-muted-foreground">
                    {t.gateway}
                </span>
            ),
        },
        {
            key: "status",
            header: "Status",
            render: (t) => <StatusBadge status={t.status} />,
        },
        {
            key: "createdAt",
            header: "Date",
            render: (t) => (
                <div className="flex flex-col text-xs text-muted-foreground">
                    <span>{t.createdAt}</span>
                    {t.completedAt && (
                        <span className="text-[11px] text-emerald-600 dark:text-emerald-400">
                            Completed: {t.completedAt}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: "actions",
            header: "",
            align: "right",
            render: (t) => {
                const isVerifying = verifyingId === t.id;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {t.status === "pending" || t.status === "failed" ? (
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 text-xs text-amber-600 hover:bg-amber-500/10 hover:text-amber-700 dark:text-amber-400"
                                onClick={() => handleVerify(t.id)}
                                disabled={isVerifying}
                                title="Reverify payment with gateway API"
                            >
                                <RefreshCw className={`mr-1 size-3.5 ${isVerifying ? "animate-spin" : ""}`} />
                                {isVerifying ? "Verifying..." : "Verify"}
                            </Button>
                        ) : null}
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 px-2 text-muted-foreground hover:text-foreground"
                            onClick={() => setViewingTopup(t)}
                            title="View details"
                        >
                            <Eye className="size-4" />
                        </Button>
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Admin — Wallet Top-ups" />
            <div className="flex flex-1 flex-col gap-6 p-4 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <PageHeader
                        title="Wallet Top-ups"
                        description="Monitor self-service wallet top-ups, track gateway payments, and reverify pending deposits."
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => exportCsv(selectedIds.length > 0)}
                            className="h-9"
                        >
                            <Download className="mr-1.5 size-4" />
                            {selectedIds.length > 0
                                ? `Export Selected (${selectedIds.length})`
                                : "Export CSV"}
                        </Button>
                    </div>
                </div>

                {/* KPI stats */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "success" })}
                    >
                        <StatTile
                            label="Total Credited"
                            value={cedis(stats.total_volume)}
                            hint={`${stats.completed_count} successful top-ups`}
                        />
                    </div>
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "pending" })}
                    >
                        <StatTile
                            label="Pending Verification"
                            value={String(stats.pending_count)}
                            hint="Awaiting gateway callback / webhook"
                        />
                    </div>
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "failed" })}
                    >
                        <StatTile
                            label="Failed Top-ups"
                            value={String(stats.failed_count)}
                            hint="Declined or abandoned charges"
                        />
                    </div>
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "all" })}
                    >
                        <StatTile
                            label="Total Recorded"
                            value={String(stats.total_count)}
                            hint="All self-service top-up attempts"
                        />
                    </div>
                </div>

                {/* Table container with filters */}
                <div className="flex-1 rounded-xl border border-border bg-card shadow-xs min-w-0">
                    <div className="flex flex-wrap items-center gap-3 border-b border-border p-4">
                        <Select
                            value={filters.status ?? "all"}
                            onValueChange={(v) => apply({ status: v })}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.gateway ?? "all"}
                            onValueChange={(v) => apply({ gateway: v })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {GATEWAY_OPTIONS.map((g) => (
                                    <SelectItem key={g.value} value={g.value}>
                                        {g.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <div className="relative ml-auto w-full sm:w-80">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => onSearchChange(e.target.value)}
                                placeholder="Search reference, agent, phone…"
                                className="pl-9 bg-background"
                            />
                        </div>
                    </div>

                    {/* Bulk Action Bar */}
                    {selectedIds.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/40 px-4 py-2.5 text-sm animate-in fade-in duration-150">
                            <span className="font-medium text-foreground">
                                {selectedIds.length} selected
                            </span>
                            <div className="ml-auto flex items-center gap-1.5">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs font-medium"
                                    onClick={() => exportCsv(true)}
                                >
                                    <Download className="mr-1 size-3.5" /> Export Selected ({selectedIds.length})
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={() => setSelectedIds([])}
                                >
                                    Clear
                                </Button>
                            </div>
                        </div>
                    )}

                    <DataTable
                        columns={columns}
                        rows={topups.data}
                        rowKey={(t) => t.id}
                        emptyMessage="No wallet top-ups found."
                        selectedIds={selectedIds}
                        onSelectionChange={setSelectedIds}
                    />

                    {topups.links.length > 3 && (
                        <div className="border-t border-border p-4">
                            <Pagination links={topups.links} />
                        </div>
                    )}
                </div>
            </div>

            {/* Top-up Detail Dialog */}
            <Dialog open={viewingTopup !== null} onOpenChange={(open) => !open && setViewingTopup(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base font-semibold">
                            <ArrowDownToLine className="size-4.5 text-brand" /> Top-up Details
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground font-mono">
                            {viewingTopup?.reference}
                        </DialogDescription>
                    </DialogHeader>

                    {viewingTopup && (
                        <div className="space-y-3.5 py-2 text-sm">
                            <div className="grid grid-cols-2 gap-2 rounded-xl bg-muted/50 p-3">
                                <div>
                                    <span className="text-xs text-muted-foreground">Credited Amount</span>
                                    <p className="text-base font-bold text-foreground">
                                        {cedis(viewingTopup.amount)}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs text-muted-foreground">Charged Gross</span>
                                    <p className="text-base font-semibold text-foreground">
                                        {cedis(viewingTopup.chargedAmount)}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2 text-xs">
                                <div className="flex justify-between border-b border-border/50 py-1.5">
                                    <span className="text-muted-foreground">Status</span>
                                    <StatusBadge status={viewingTopup.status} />
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1.5">
                                    <span className="text-muted-foreground">Agent</span>
                                    <span className="font-medium text-foreground">{viewingTopup.agentName}</span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1.5">
                                    <span className="text-muted-foreground">Phone</span>
                                    <span className="font-mono text-foreground">{viewingTopup.agentPhone}</span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1.5">
                                    <span className="text-muted-foreground">Payment Gateway</span>
                                    <span className="font-semibold uppercase text-foreground">
                                        {viewingTopup.gateway}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1.5">
                                    <span className="text-muted-foreground">Gateway Ref</span>
                                    <span className="font-mono text-foreground">
                                        {viewingTopup.gatewayReference ?? "—"}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1.5">
                                    <span className="text-muted-foreground">Initiated At</span>
                                    <span className="text-foreground">{viewingTopup.createdAt}</span>
                                </div>
                                {viewingTopup.completedAt && (
                                    <div className="flex justify-between border-b border-border/50 py-1.5">
                                        <span className="text-muted-foreground">Completed At</span>
                                        <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                            {viewingTopup.completedAt}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    <DialogFooter className="gap-2 sm:gap-0">
                        {viewingTopup && (viewingTopup.status === "pending" || viewingTopup.status === "failed") && (
                            <Button
                                variant="outline"
                                className="mr-auto text-xs text-amber-600 hover:bg-amber-500/10 hover:text-amber-700"
                                onClick={() => {
                                    handleVerify(viewingTopup.id);
                                    setViewingTopup(null);
                                }}
                            >
                                <RefreshCw className="mr-1 size-3.5" /> Reverify with Gateway
                            </Button>
                        )}
                        <Button variant="secondary" onClick={() => setViewingTopup(null)}>
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
