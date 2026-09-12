import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import {
    BaseCostPanel,
    type BaseCostRow,
} from "@/components/admin/pricing/base-cost-panel";
import {
    TierManagementPanel,
    type TierRow,
} from "@/components/admin/pricing/tier-management-panel";
import {
    type Tier,
    TierPricePanel,
} from "@/components/admin/pricing/tier-price-panel";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { PageHeader } from "@/components/common/page-header";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const NETWORKS = [
    { id: "default", label: "Default Fallback" },
    { id: "mtn", label: "MTN" },
    { id: "telecel", label: "Telecel" },
    { id: "at", label: "AT" },
] as const;

export default function AdminPricing({
    baseCosts,
    tiers,
    tierList,
}: {
    baseCosts: BaseCostRow[];
    tiers: Tier[];
    tierList: TierRow[];
}) {
    const [activeNetwork, setActiveNetwork] = useState<string>("mtn");
    const [cloning, setCloning] = useState(false);
    const [resetting, setResetting] = useState(false);

    const activeLabel =
        NETWORKS.find((n) => n.id === activeNetwork)?.label ??
        activeNetwork.toUpperCase();

    const handleClone = () => {
        router.post(
            "/admin/pricing/clone-network",
            { target_network: activeNetwork },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setCloning(false),
            },
        );
    };

    const handleReset = () => {
        router.post(
            "/admin/pricing/reset-network",
            { target_network: activeNetwork },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setResetting(false),
            },
        );
    };

    return (
        <>
            <Head title="Admin — Pricing" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Pricing"
                    description="What we pay Databundleshub (base cost) and what each tier charges. The base cost is the floor under every selling rate."
                />

                <TierManagementPanel tiers={tierList} />

                {/* Network Tabs */}
                <div className="flex flex-wrap items-center gap-2">
                    {NETWORKS.map((net) => (
                        <button
                            key={net.id}
                            type="button"
                            onClick={() => setActiveNetwork(net.id)}
                            className={cn(
                                "rounded-md px-4 py-2 text-sm font-medium transition-colors",
                                activeNetwork === net.id
                                    ? "bg-brand text-brand-fg"
                                    : "bg-muted text-muted-foreground hover:bg-muted/80",
                            )}
                        >
                            {net.label}
                        </button>
                    ))}
                </div>

                {/* Network Actions for non-MTN, non-Default */}
                {activeNetwork !== "mtn" && activeNetwork !== "default" ? (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center p-4 rounded-lg border bg-card">
                        <div className="flex-1">
                            <h3 className="font-semibold">
                                Network Actions — {activeLabel}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Clone MTN bands into this network, or remove all
                                network-specific bands.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setCloning(true)}
                            >
                                Clone from MTN
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => setResetting(true)}
                            >
                                Reset network
                            </Button>
                        </div>
                    </div>
                ) : null}

                <BaseCostPanel rows={baseCosts} activeNetwork={activeNetwork} />
                <TierPricePanel tiers={tiers} activeNetwork={activeNetwork} />
            </div>

            <ConfirmDialog
                open={cloning}
                onOpenChange={setCloning}
                title={`Clone MTN pricing to ${activeLabel}?`}
                description="This will clear any existing base costs and tier rates for this network and replace them with an exact copy of the MTN setup."
                confirmLabel="Clone from MTN"
                onConfirm={handleClone}
            />

            <ConfirmDialog
                open={resetting}
                onOpenChange={setResetting}
                title={`Reset ${activeLabel} pricing?`}
                description="This will delete all base costs and tier rates for this network. This action cannot be undone."
                confirmLabel="Reset network"
                onConfirm={handleReset}
            />
        </>
    );
}
