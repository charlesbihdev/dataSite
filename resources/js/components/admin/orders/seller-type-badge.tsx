// Distinct highlight per seller type so agent vs. subagent orders stand out at a glance.
const STYLES: Record<string, string> = {
    Agent: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    Subagent: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    Customer: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
};

export function SellerTypeBadge({ type }: { type: string }) {
    const style = STYLES[type] ?? 'bg-muted text-muted-foreground';

    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${style}`}>
            {type}
        </span>
    );
}
