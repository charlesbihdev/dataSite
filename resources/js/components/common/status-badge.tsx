import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

// One place every status colour is decided (ENGINEERING_PRINCIPLES §1). Tones map to semantic
// tokens only — never a raw palette value.
const statusBadge = cva(
    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize',
    {
        variants: {
            tone: {
                success: 'bg-success/10 text-success',
                warning: 'bg-warning/20 text-warning-foreground',
                danger: 'bg-danger/10 text-danger',
                info: 'bg-info/10 text-info',
                neutral: 'bg-muted text-muted-foreground',
            },
        },
        defaultVariants: { tone: 'neutral' },
    },
);

type Tone = NonNullable<VariantProps<typeof statusBadge>['tone']>;

// Maps every domain status we render to a tone. Unknown values fall back to neutral.
const STATUS_TONE: Record<string, Tone> = {
    // orders
    completed: 'success',
    processing: 'info',
    pending: 'warning',
    failed: 'danger',
    refunded: 'warning',
    // earnings
    credited: 'success',
    reversed: 'danger',
    // withdrawals
    approved: 'info',
    paid: 'success',
    rejected: 'danger',
    // top-ups
    success: 'success',
    // accounts
    active: 'success',
    suspended: 'danger',
    inactive: 'neutral',
};

export function StatusBadge({ status, className }: { status: string; className?: string }) {
    const tone = STATUS_TONE[status.toLowerCase()] ?? 'neutral';
    return <span className={cn(statusBadge({ tone }), className)}>{status}</span>;
}
