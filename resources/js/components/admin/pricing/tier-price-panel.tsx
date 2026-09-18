import { router, useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
    destroyTierPrice,
    storeTierPrice,
    updateTierPrice,
} from "@/actions/App/Http/Controllers/Admin/PricingController";
import {
    BandDialog,
    BandFormValue,
} from "@/components/admin/pricing/band-dialog";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Column, DataTable } from "@/components/common/data-table";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cedis } from "@/lib/format";

export interface TierPriceRow {
    id: number;
    tierId: number;
    network: string;
    minGb: number;
    maxGb: number;
    pricePerGb: number;
    isActive: boolean;
}

export interface Tier {
    id: number;
    name: string;
    isActive: boolean;
    prices: TierPriceRow[];
}

interface FlatRow extends TierPriceRow {
    tierName: string;
}

const emptyBand: BandFormValue = {
    pricing_tier_id: "",
    network: "mtn",
    min_gb: "",
    max_gb: "",
    rate: "",
    is_active: true,
};

export function TierPricePanel({
    tiers,
    activeNetwork,
}: {
    tiers: Tier[];
    activeNetwork: string;
}) {
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<FlatRow | null>(null);
    const form = useForm<BandFormValue>(emptyBand);

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(destroyTierPrice(deleteTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    const rows = useMemo<FlatRow[]>(
        () =>
            tiers.flatMap((t) =>
                t.prices
                    .filter((p) => p.network === activeNetwork)
                    .map((p) => ({ ...p, tierName: t.name })),
            ),
        [tiers, activeNetwork],
    );

    const openAdd = () => {
        setEditingId(null);
        form.setData({ ...emptyBand, network: activeNetwork });
        setOpen(true);
    };

    const openEdit = (row: FlatRow) => {
        setEditingId(row.id);
        form.setData({
            pricing_tier_id: String(row.tierId),
            network: row.network,
            min_gb: String(row.minGb),
            max_gb: String(row.maxGb),
            rate: String(row.pricePerGb),
            is_active: row.isActive,
        });
        setOpen(true);
    };

    const columns: Column<FlatRow>[] = [
        { key: "tierName", header: "Tier" },
        {
            key: "range",
            header: "Range",
            render: (r) => `${r.minGb}–${r.maxGb} GB`,
        },
        {
            key: "pricePerGb",
            header: "Price / GB",
            align: "right",
            render: (r) => cedis(r.pricePerGb),
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
                    <CardTitle className="text-base">
                        Selling rates by tier
                    </CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Our per-GB rate per tier. Can't be saved below the base
                        cost it covers.
                    </p>
                </div>
                <Button onClick={openAdd}>Add rate</Button>
            </CardHeader>
            <CardContent>
                <DataTable
                    columns={columns}
                    rows={rows}
                    rowKey={(r) => r.id}
                    emptyMessage={`No selling rates for ${activeNetwork.toUpperCase()} yet.`}
                />
            </CardContent>

            <BandDialog
                open={open}
                onOpenChange={setOpen}
                title={editingId ? "Edit selling rate" : "Add selling rate"}
                rateLabel="Price per GB"
                rateErrorKey="price_per_gb"
                form={form}
                tiers={tiers.map((t) => ({ id: t.id, name: t.name }))}
                onSubmit={() => {
                    const opts = {
                        onSuccess: () => setOpen(false),
                        preserveScroll: true,
                    };
                    form.transform((d) => ({
                        pricing_tier_id: d.pricing_tier_id,
                        network: d.network,
                        min_gb: d.min_gb,
                        max_gb: d.max_gb,
                        price_per_gb: d.rate,
                        is_active: d.is_active,
                    }));
                    if (editingId) {
                        form.put(updateTierPrice(editingId).url, opts);
                    } else {
                        form.post(storeTierPrice().url, opts);
                    }
                }}
            />

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(o) => !o && setDeleteTarget(null)}
                title={
                    deleteTarget
                        ? `Delete ${deleteTarget.tierName} ${deleteTarget.minGb}–${deleteTarget.maxGb} GB rate?`
                        : ""
                }
                description="This selling rate will be removed for this tier. This action cannot be undone."
                onConfirm={handleDelete}
            />
        </Card>
    );
}
