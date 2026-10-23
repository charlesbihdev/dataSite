import { Link } from '@inertiajs/react';
import { index as resellersIndex, splits as profitSplits } from '@/actions/App/Http/Controllers/Admin/ResellersController';
import { cn } from '@/lib/utils';

const tabClass = (active: boolean) =>
    cn(
        'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
        active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
    );

/** Switches between the two Resellers views, styled like the Agent/Regular orders toggle. */
export function ResellersTabs({ active }: { active: 'tree' | 'splits' }) {
    return (
        <div className="flex items-center gap-1 rounded-xl border border-border p-1">
            <Link href={resellersIndex.url()} className={tabClass(active === 'tree')}>
                Agents &amp; Subagents
            </Link>
            <Link href={profitSplits.url()} className={tabClass(active === 'splits')}>
                Profit Splits
            </Link>
        </div>
    );
}
