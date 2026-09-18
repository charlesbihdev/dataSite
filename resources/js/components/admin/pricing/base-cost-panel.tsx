import { router, useForm } from "@inertiajs/react";
import { useState } from "react";
import {
    destroyBaseCost,
    storeBaseCost,
    updateBaseCost,
} from "@/actions/App/Http/Controllers/Admin/PricingController";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Column, DataTable } from "@/components/common/data-table";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    BandDialog,
    BandFormValue,
} from "@/components/admin/pricing/band-dialog";
import { cedis } from "@/lib/format";

export interface BaseCostRow {
    id: number;
    network: string;
    minGb: number;
    maxGb: number;
    costPerGb: number;
    isActive: boolean;
}

const emptyBand: BandFormValue = {
    network: "mtn",
    min_gb: "",
    max_gb: "",
    rate: "",
    is_active: true,
};

export function BaseCostPanel({
    rows,
    activeNetwork,
}: {
    rows: BaseCostRow[];
    activeNetwork: string;
}) {
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<BaseCostRow | null>(null);
    const form = useForm<BandFormValue>(emptyBand);

    const filteredRows = rows.filter((r) => r.network === activeNetwork);

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(destroyBaseCost(deleteTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    const openAdd = () => {
        setEditingId(null);
        form.setData({ ...emptyBand, network: activeNetwork });
        setOpen(true);
    };

    const openEdit = (row: BaseCostRow) => {
        setEditingId(row.id);
        form.setData({
            network: row.network,
            min_gb: String(row.minGb),
            max_gb: String(row.maxGb),
            rate: String(row.costPerGb),
            is_active: row.isActive,
        });
        setOpen(true);
    };

    const columns: Column<BaseCostRow>[] = [
        {
            key: "range",
            header: "Range",
            render: (r) => `${r.minGb}–${r.maxGb} GB`,
        },
        {
            key: "costPerGb",
            header: "Cost / GB",
            align: "right",
            render: (r) => cedis(r.costPerGb),
        },
        {
            key: "isActive",
            header: "Status",
            render: (r) => (
                <StatusBadge status={r.isActive ? "active" : "inactive"} />
            ),
        },
        {
            key: "actions",
            header: "",
            align: "right",
            render: (r) => (
                <div className="flex justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => openEdit(r)}
                    >
                        Edit
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-danger"
                        onClick={() => setDeleteTarget(r)}
                    >
                        Delete
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle className="text-base">Base cost</CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        What we pay Databundleshub per GB.
                    </p>
                </div>
                <Button onClick={openAdd}>Add band</Button>
            </CardHeader>
            <CardContent>
                <DataTable
                    columns={columns}
                    rows={filteredRows}
                    rowKey={(r) => r.id}
                    emptyMessage={`No base cost bands for ${activeNetwork.toUpperCase()}.`}
                />
            </CardContent>

            <BandDialog
                open={open}
                onOpenChange={setOpen}
                title={editingId ? "Edit base cost band" : "Add base cost band"}
                rateLabel="Cost per GB"
                rateErrorKey="cost_per_gb"
                form={form}
                onSubmit={() => {
                    const opts = {
                        onSuccess: () => setOpen(false),
                        preserveScroll: true,
                    };
                    form.transform((d) => ({
                        network: d.network,
                        min_gb: d.min_gb,
                        max_gb: d.max_gb,
                        cost_per_gb: d.rate,
                        is_active: d.is_active,
                    }));
                    if (editingId) {
                        form.put(updateBaseCost(editingId).url, opts);
                    } else {
                        form.post(storeBaseCost().url, opts);
                    }
                }}
            />

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(o) => !o && setDeleteTarget(null)}
                title={
                    deleteTarget
                        ? `Delete ${deleteTarget.minGb}–${deleteTarget.maxGb} GB base cost band?`
                        : ""
                }
                description="This base cost band will be removed. It is the floor under every selling rate for this range. This action cannot be undone."
                onConfirm={handleDelete}
            />
        </Card>
    );
}
