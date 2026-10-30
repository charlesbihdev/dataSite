import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { NotificationDialog } from '@/components/agent/notifications/notification-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { friendlyDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { read, readAll } from '@/routes/agent/notifications';
import type { AgentNotification } from '@/types/notifications';

const reloadOptions = { preserveScroll: true, preserveState: true } as const;

/**
 * Top-right bell for agents: unread badge, the latest admin notifications with friendly dates,
 * click one to read it in full (marks it read), or mark all read. Renders nothing outside the agent portal.
 */
export function NotificationBell() {
    const { notifications } = usePage().props;
    const [opened, setOpened] = useState<AgentNotification | null>(null);

    if (!notifications) {
        return null;
    }

    const open = (n: AgentNotification) => {
        setOpened(n);
        if (!n.read) {
            router.post(read.url(n.id), {}, reloadOptions);
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="relative"
                        aria-label={notifications.unread > 0 ? `Notifications, ${notifications.unread} unread` : 'Notifications'}
                    >
                        <Bell className="size-5" />
                        {notifications.unread > 0 ? (
                            <span className="absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-danger px-1 text-[10px] leading-none font-semibold text-danger-foreground">
                                {notifications.unread > 99 ? '99+' : notifications.unread}
                            </span>
                        ) : null}
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className="w-80 p-0">
                    <div className="flex items-center justify-between px-3 py-2">
                        <DropdownMenuLabel className="p-0 text-sm">Notifications</DropdownMenuLabel>
                        {notifications.unread > 0 ? (
                            <button
                                type="button"
                                className="text-xs font-medium text-brand hover:underline"
                                onClick={() => router.post(readAll.url(), {}, reloadOptions)}
                            >
                                Mark all as read
                            </button>
                        ) : null}
                    </div>
                    <DropdownMenuSeparator className="my-0" />

                    {notifications.items.length === 0 ? (
                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">No notifications yet.</p>
                    ) : (
                        <div className="max-h-96 overflow-y-auto py-1">
                            {notifications.items.map((n) => (
                                <DropdownMenuItem
                                    key={n.id}
                                    onSelect={() => open(n)}
                                    className={cn('flex cursor-pointer items-start gap-2 px-3 py-2', !n.read && 'bg-brand-subtle')}
                                >
                                    <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : 'bg-brand')} />
                                    <span className="min-w-0 flex-1">
                                        <span className={cn('block truncate text-sm', !n.read && 'font-semibold')}>{n.title}</span>
                                        <span className="line-clamp-2 text-xs text-muted-foreground">{n.body}</span>
                                        <span className="mt-0.5 block text-[11px] text-muted-foreground">{friendlyDate(n.createdAt)}</span>
                                    </span>
                                </DropdownMenuItem>
                            ))}
                        </div>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <NotificationDialog notification={opened} onClose={() => setOpened(null)} />
        </>
    );
}
