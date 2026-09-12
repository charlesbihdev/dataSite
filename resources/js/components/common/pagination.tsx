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
