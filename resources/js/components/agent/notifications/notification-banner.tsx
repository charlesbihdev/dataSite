import { router, usePage } from '@inertiajs/react';
import { Bell, X } from 'lucide-react';
import { friendlyDate } from '@/lib/format';
import { read } from '@/routes/agent/notifications';

/**
 * Full-width banner at the top of the agent dashboard with the newest unread admin notification in
 * full. Dismissing marks it read (the bell count drops); the next unread one then takes its place.
 */
export function NotificationBanner() {
    const { notifications } = usePage().props;
    const newest = notifications?.items.find((n) => !n.read);

    if (!newest) {
        return null;
    }

    return (
        <div role="status" className="relative flex gap-3 rounded-xl border border-brand/30 bg-brand-subtle p-4 pr-12">
            <Bell className="mt-0.5 size-5 shrink-0 text-brand" />
            <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-baseline gap-x-2 font-semibold text-foreground">
                    {newest.title}
                    <span className="text-xs font-normal text-muted-foreground">{friendlyDate(newest.createdAt)}</span>
                </p>
                <p className="mt-1 text-sm whitespace-pre-line text-foreground">{newest.body}</p>
            </div>
            <button
                type="button"
                aria-label="Dismiss notification"
                className="absolute top-3 right-3 rounded-md p-1 text-muted-foreground transition hover:bg-background hover:text-foreground"
                onClick={() => router.post(read.url(newest.id), {}, { preserveScroll: true, preserveState: true })}
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
