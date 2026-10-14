import { Head, router } from "@inertiajs/react";
import { Check, CheckCircle2, Clock, Copy, Download, RefreshCw, Send, Trash2, Users } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { PageLink, Pagination } from "@/components/common/pagination";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export interface Beneficiary {
    id: number;
    phone: string;
    network: string | null;
    networkLabel: string | null;
    sourceLabel: string;
    status: "pending" | "submitted" | "approved";
    failureMessage: string | null;
    checkCount: number;
    submittedAt: string | null;
    approvedAt: string | null;
    lastCheckedAt: string | null;
    createdAt: string | null;
}

interface Props {
    beneficiaries: { data: Beneficiary[]; links: PageLink[] };
    counts: {
        total: number;
        pending: number;
        submitted: number;
        approved: number;
    };
    filters: {
        status: string;
        network: string;
        q: string;
    };
}

const STATUSES = [
    { value: "all", label: "All Statuses" },
    { value: "pending", label: "Pending (Unsubmitted)" },
    { value: "submitted", label: "Submitted to DBH" },
    { value: "approved", label: "Approved" },
];

const NETWORKS = [
    { value: "all", label: "All Networks" },
    { value: "mtn", label: "MTN" },
    { value: "telecel", label: "Telecel" },
    { value: "at", label: "AT (AirtelTigo)" },
];

export default function AdminBeneficiaries({ beneficiaries, counts, filters }: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
    const [copiedPhone, setCopiedPhone] = useState<string | null>(null);
    const [copiedAll, setCopiedAll] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [isRechecking, setIsRechecking] = useState(false);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (patch: Record<string, string | null>) => {
        router.get(
            "/admin/beneficiaries",
            {
                status: filters.status ?? "all",
                network: filters.network ?? "all",
                q: search,
                ...patch,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const onSearchChange = (value: string) => {
        setSearch(value);
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => apply({ q: value || null }), 300);
    };

    useEffect(() => setSearch(filters.q ?? ""), [filters.q]);

    const copyPhone = (phone: string) => {
        navigator.clipboard.writeText(phone).then(() => {
            setCopiedPhone(phone);
            setTimeout(() => setCopiedPhone(null), 1500);
        });
    };

    // Copy pending numbers or selected numbers to clipboard (newline-separated)
    const copyNumbers = (phones: string[]) => {
        if (phones.length === 0) return;
        navigator.clipboard.writeText(phones.join("\n")).then(() => {
            setCopiedAll(true);
            setTimeout(() => setCopiedAll(false), 2000);
        });
    };

    const handleMarkSubmitted = (ids: (string | number)[]) => {
        if (ids.length === 0) return;
        router.post(
            "/admin/beneficiaries/mark-submitted",
            { ids },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedIds([]),
            },
        );
    };

    const handleMarkApproved = (ids: (string | number)[]) => {
        if (ids.length === 0) return;
        router.post(
            "/admin/beneficiaries/mark-approved",
            { ids },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedIds([]),
            },
        );
    };

    const handleRecheck = (ids: (string | number)[]) => {
        if (ids.length === 0 || isRechecking) return;
        setIsRechecking(true);
        router.post(
            "/admin/beneficiaries/recheck",
            { ids },
            {
                preserveScroll: true,
                onFinish: () => setIsRechecking(false),
            },
        );
    };

    const handleDelete = (id: number) => {
        router.delete(`/admin/beneficiaries/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingId(null);
                setSelectedIds((prev) => prev.filter((item) => item !== id));
            },
        });
    };

    const handleBulkDelete = () => {
        router.delete("/admin/beneficiaries/bulk", {
            data: { ids: selectedIds },
            preserveScroll: true,
            onSuccess: () => {
                setBulkDeleting(false);
                setSelectedIds([]);
            },
        });
    };

    // Trigger Excel / CSV download with active filters
    const exportExcel = (markSubmitted = false) => {
        const params = new URLSearchParams();
        if (filters.status && filters.status !== "all") params.set("status", filters.status);
        if (filters.network && filters.network !== "all") params.set("network", filters.network);
        if (search) params.set("q", search);
        if (markSubmitted) params.set("mark_submitted", "1");

        window.location.href = `/admin/beneficiaries/export?${params.toString()}`;
    };

    // Get phone numbers of selected items
    const selectedPhones = beneficiaries.data
        .filter((b) => selectedIds.includes(b.id))
        .map((b) => b.phone);

    // Get phone numbers of all unsubmitted items currently displayed
    const pendingPhonesOnPage = beneficiaries.data
        .filter((b) => b.status === "pending")
        .map((b) => b.phone);

    const columns: Column<Beneficiary>[] = [
        {
            key: "phone",
            header: "Phone Number",
            render: (b) => (
                <div className="flex items-center gap-1.5 font-mono text-sm font-medium">
                    <span>{b.phone}</span>
                    <button
                        type="button"
                        onClick={() => copyPhone(b.phone)}
                        className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        title="Copy phone"
                    >
                        {copiedPhone === b.phone ? <Check className="size-3.5 text-success" /> : <Copy className="size-3.5" />}
                    </button>
                </div>
            ),
        },
        {
            key: "network",
            header: "Network",
            render: (b) => (
                <span className="uppercase text-xs font-semibold px-2 py-0.5 rounded-md bg-muted">
                    {b.network ?? "UNKNOWN"}
                </span>
            ),
        },
        {
            key: "sourceLabel",
            header: "Source Store",
            render: (b) => <span className="text-sm font-medium text-foreground">{b.sourceLabel}</span>,
        },
        {
            key: "status",
            header: "Submission Status",
            render: (b) => {
                if (b.status === "pending") {
                    return (
                        <div className="flex items-center gap-1.5">
                            <StatusBadge status="pending" />
                            <span className="text-xs text-muted-foreground">Unsubmitted</span>
                        </div>
                    );
                }
                if (b.status === "submitted") {
                    return (
                        <div className="flex flex-col">
                            <StatusBadge status="submitted" />
                            {b.submittedAt && <span className="text-[11px] text-muted-foreground mt-0.5">{b.submittedAt}</span>}
                        </div>
                    );
                }
                return (
                    <div className="flex flex-col">
                        <StatusBadge status="approved" />
                        {b.approvedAt && <span className="text-[11px] text-muted-foreground mt-0.5">{b.approvedAt}</span>}
                    </div>
                );
            },
        },
        {
            key: "checkCount",
            header: "Checks",
            align: "right",
            render: (b) => (
                <span className="tabular-nums font-mono text-xs text-muted-foreground">
                    {b.checkCount}x
                </span>
            ),
        },
        {
            key: "lastCheckedAt",
            header: "Last Checked",
            render: (b) => <span className="text-xs text-muted-foreground">{b.lastCheckedAt ?? "—"}</span>,
        },
        {
            key: "actions",
            header: "",
            align: "right",
            render: (b) => (
                <div className="flex items-center justify-end gap-1">
                    {b.status === "pending" && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 text-xs text-info hover:text-info hover:bg-info/10"
                            onClick={() => handleMarkSubmitted([b.id])}
                            title="Mark as submitted to DBH portal"
                        >
                            <Send className="mr-1 size-3" /> Mark Submitted
                        </Button>
                    )}
                    {b.status === "submitted" && (
                        <>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 text-xs"
                                onClick={() => handleRecheck([b.id])}
                                disabled={isRechecking}
                                title="Verify live against DBH API"
                            >
                                <RefreshCw className={`mr-1 size-3 ${isRechecking ? "animate-spin" : ""}`} /> Check DBH
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 text-xs text-success hover:text-success hover:bg-success/10"
                                onClick={() => handleMarkApproved([b.id])}
                                title="Mark as approved"
                            >
                                <CheckCircle2 className="mr-1 size-3" /> Approve
                            </Button>
                        </>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 text-xs text-destructive hover:text-destructive hover:bg-destructive/10"
                        onClick={() => setDeletingId(b.id)}
                        title="Remove record"
                    >
                        <Trash2 className="size-3.5" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Admin — Beneficiaries" />
            <div className="flex flex-1 flex-col gap-6 p-4 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <PageHeader
                        title="Beneficiaries (Unapproved Numbers)"
                        description="Track numbers that returned unapproved on storefronts. Export to Excel or submit them to the Databundleshub supplier portal for approval."
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        {pendingPhonesOnPage.length > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => copyNumbers(pendingPhonesOnPage)}
                                className="h-9"
                            >
                                {copiedAll ? <Check className="mr-1.5 size-4 text-success" /> : <Copy className="mr-1.5 size-4" />}
                                Copy Pending ({pendingPhonesOnPage.length})
                            </Button>
                        )}

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => exportExcel(false)}
                            className="h-9"
                        >
                            <Download className="mr-1.5 size-4" /> Export Excel (.csv)
                        </Button>

                        {counts.pending > 0 && (
                            <Button
                                size="sm"
                                onClick={() => exportExcel(true)}
                                className="h-9 bg-brand text-brand-fg hover:bg-brand/90"
                            >
                                <Send className="mr-1.5 size-4" /> Export & Mark Submitted
                            </Button>
                        )}
                    </div>
                </div>

                {/* Status KPI summary tiles */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "pending" })}
                    >
                        <StatTile
                            label="Pending (Unsubmitted)"
                            value={String(counts.pending)}
                            hint="Awaiting submission to DBH"
                        />
                    </div>
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "submitted" })}
                    >
                        <StatTile
                            label="Submitted to DBH"
                            value={String(counts.submitted)}
                            hint="Submitted, awaiting DBH approval"
                        />
                    </div>
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "approved" })}
                    >
                        <StatTile
                            label="Approved"
                            value={String(counts.approved)}
                            hint="Ready for instant ordering"
                        />
                    </div>
                    <div
                        className="cursor-pointer transition hover:opacity-90"
                        onClick={() => apply({ status: "all" })}
                    >
                        <StatTile
                            label="Total Captured"
                            value={String(counts.total)}
                            hint="All recorded storefront numbers"
                        />
                    </div>
                </div>

                {/* Table container with filters & multi-select actions */}
                <div className="flex-1 rounded-xl border border-border bg-card shadow-xs min-w-0">
                    <div className="flex flex-wrap items-center gap-3 border-b border-border p-4">
                        <Select
                            value={filters.status ?? "all"}
                            onValueChange={(v) => apply({ status: v })}
                        >
                            <SelectTrigger className="w-52">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.network ?? "all"}
                            onValueChange={(v) => apply({ network: v })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {NETWORKS.map((n) => (
                                    <SelectItem key={n.value} value={n.value}>
                                        {n.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Search phone or store name…"
                            className="ml-auto w-full bg-background sm:w-80"
                        />
                    </div>

                    {/* Bulk Action Bar (appears when rows are selected) */}
                    {selectedIds.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/40 px-4 py-2.5 text-sm animate-in fade-in duration-150">
                            <span className="font-medium text-foreground">
                                {selectedIds.length} selected
                            </span>
                            <div className="ml-auto flex flex-wrap items-center gap-1.5">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={() => copyNumbers(selectedPhones)}
                                >
                                    <Copy className="mr-1 size-3.5" /> Copy Phones
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs text-info hover:text-info"
                                    onClick={() => handleMarkSubmitted(selectedIds)}
                                >
                                    <Send className="mr-1 size-3.5" /> Mark Submitted
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={() => handleRecheck(selectedIds)}
                                    disabled={isRechecking}
                                >
                                    <RefreshCw className={`mr-1 size-3.5 ${isRechecking ? "animate-spin" : ""}`} /> Check DBH
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs text-success hover:text-success"
                                    onClick={() => handleMarkApproved(selectedIds)}
                                >
                                    <CheckCircle2 className="mr-1 size-3.5" /> Approve
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs text-destructive hover:text-destructive"
                                    onClick={() => setBulkDeleting(true)}
                                >
                                    <Trash2 className="mr-1 size-3.5" /> Delete
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
                        rows={beneficiaries.data}
                        rowKey={(b) => b.id}
                        emptyMessage="No beneficiary records found."
                        selectedIds={selectedIds}
                        onSelectionChange={setSelectedIds}
                    />

                    {beneficiaries.links.length > 3 && (
                        <div className="border-t border-border p-4">
                            <Pagination links={beneficiaries.links} />
                        </div>
                    )}
                </div>
            </div>

            {/* Confirm single delete dialog */}
            <ConfirmDialog
                open={deletingId !== null}
                onOpenChange={(open) => !open && setDeletingId(null)}
                title="Delete Record"
                description="Are you sure you want to remove this beneficiary record? This cannot be undone."
                confirmLabel="Delete"
                variant="destructive"
                onConfirm={() => deletingId && handleDelete(deletingId)}
            />

            {/* Confirm bulk delete dialog */}
            <ConfirmDialog
                open={bulkDeleting}
                onOpenChange={(open) => !open && setBulkDeleting(false)}
                title="Delete Selected Records"
                description={`Are you sure you want to delete ${selectedIds.length} selected beneficiary records?`}
                confirmLabel="Delete All"
                variant="destructive"
                onConfirm={handleBulkDelete}
            />
        </>
    );
}

