import { router, useForm } from "@inertiajs/react";
import { useState } from "react";
import {
    destroyTier,
    storeTier,
    updateTier,
} from "@/actions/App/Http/Controllers/Admin/PricingController";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Column, DataTable } from "@/components/common/data-table";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { TierFormDialog, type TierFormValue } from "./tier-form-dialog";

export interface TierRow {
    id: number;
    name: string;
    isActive: boolean;
    isDefault?: boolean;
    isUndeletable?: boolean;
    agentsCount: number;
}

const emptyTier: TierFormValue = { name: "", is_active: true };

export function TierManagementPanel({ tiers }: { tiers: TierRow[] }) {
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<TierRow | null>(null);
    const form = useForm<TierFormValue>(emptyTier);

    const openAdd = () => {
        setEditingId(null);
        form.setData(emptyTier);
        setOpen(true);
    };

    const openEdit = (tier: TierRow) => {
        setEditingId(tier.id);
        form.setData({ name: tier.name, is_active: tier.isActive });
        setOpen(true);
    };

    const handleSubmit = () => {
        const opts = {
            onSuccess: () => setOpen(false),
            preserveScroll: true,
        };
        if (editingId) {
            form.put(updateTier({ pricingTier: editingId }).url, opts);
        } else {
            form.post(storeTier().url, opts);
        }
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(destroyTier({ pricingTier: deleteTarget.id }).url, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    const columns: Column<TierRow>[] = [
        {
            key: "name",
            header: "Tier Name",
            render: (r) => (
                <div className="flex items-center gap-2">
                    {r.name}
                    {r.isDefault && (
                        <span className="rounded-full bg-brand/10 px-2 py-0.5 text-xs font-semibold text-brand">
                            Default
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: "agentsCount",
            header: "Agents",
            align: "right",
            render: (r) => String(r.agentsCount),
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
            render: (r) => {
                const disableDelete = r.agentsCount > 0 || r.isUndeletable;
                const deleteTitle = r.isUndeletable
                    ? "System tiers cannot be deleted"
                    : r.agentsCount > 0
                      ? "Reassign agents first"
                      : undefined;

                return (
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
                            disabled={disableDelete}
                            title={deleteTitle}
                            onClick={() => setDeleteTarget(r)}
                        >
                            Delete
                        </Button>
                    </div>
                );
            },
        },
    ];

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle className="text-base">Pricing Tiers</CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create and manage tiers that agents are assigned to.
                        Each tier has its own selling rates.
                    </p>
                </div>
                <Button onClick={openAdd}>Add tier</Button>
            </CardHeader>
            <CardContent>
                <DataTable
                    columns={columns}
                    rows={tiers}
                    rowKey={(r) => r.id}
                    emptyMessage="No pricing tiers yet."
                />
            </CardContent>

            <TierFormDialog
                open={open}
                onOpenChange={setOpen}
                title={editingId ? "Edit tier" : "New tier"}
                form={form}
                onSubmit={handleSubmit}
            />

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(o) => !o && setDeleteTarget(null)}
                title={`Delete "${deleteTarget?.name}" tier?`}
                description="All selling rates for this tier will also be removed. This action cannot be undone."
                onConfirm={handleDelete}
            />
        </Card>
    );
}
