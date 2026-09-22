import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Renders a Laravel paginator's `links` as Inertia links. Hidden when there's only one page.
 */
export function Pagination({ links }: { links: PageLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="mt-4 flex flex-wrap items-center gap-1">
            {links.map((link, i) => {
                const label = link.label.replace('&laquo;', '‹').replace('&raquo;', '›');
                const base = 'min-w-9 rounded-md px-3 py-1.5 text-sm';

                if (link.url === null) {
                    return (
                        <span key={i} className={cn(base, 'text-muted-foreground/50')} dangerouslySetInnerHTML={{ __html: label }} />
                    );
                }

                return (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        preserveState
                        className={cn(
                            base,
                            link.active
                                ? 'bg-brand text-brand-fg'
                                : 'text-foreground hover:bg-muted',
                        )}
                        dangerouslySetInnerHTML={{ __html: label }}
                    />
                );
            })}
        </nav>
    );
}

/**
 * A "simple" paginator (Laravel `simplePaginate`) — just Previous / Next, no page numbers or COUNT
 * query. Hidden entirely when there's nothing on either side. Feed it the paginator's
 * `prev_page_url` / `next_page_url`.
 */
export function SimplePagination({ prevUrl, nextUrl }: { prevUrl: string | null; nextUrl: string | null }) {
    if (!prevUrl && !nextUrl) {
        return null;
    }

    const base = 'rounded-md border border-border px-3 py-1.5 text-sm';
    const disabled = 'cursor-not-allowed text-muted-foreground/50';
    const enabled = 'text-foreground hover:bg-muted';

    return (
        <nav className="flex items-center justify-end gap-2">
            {prevUrl ? (
                <Link href={prevUrl} preserveScroll preserveState className={cn(base, enabled)}>
                    ‹ Previous
                </Link>
            ) : (
                <span className={cn(base, disabled)}>‹ Previous</span>
            )}
            {nextUrl ? (
                <Link href={nextUrl} preserveScroll preserveState className={cn(base, enabled)}>
                    Next ›
                </Link>
            ) : (
                <span className={cn(base, disabled)}>Next ›</span>
            )}
        </nav>
    );
}
