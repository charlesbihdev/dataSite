import { Head } from '@inertiajs/react';
import { BaseCostPanel, BaseCostRow } from '@/components/admin/pricing/base-cost-panel';
import { Tier, TierPricePanel } from '@/components/admin/pricing/tier-price-panel';
import { PageHeader } from '@/components/common/page-header';

export default function AdminPricing({ baseCosts, tiers }: { baseCosts: BaseCostRow[]; tiers: Tier[] }) {
    return (
        <>
            <Head title="Admin — Pricing" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Pricing"
                    description="What we pay Databundleshub (base cost) and what each tier charges. The base cost is the floor under every selling rate."
                />
                <BaseCostPanel rows={baseCosts} />
                <TierPricePanel tiers={tiers} />
            </div>
        </>
    );
}
