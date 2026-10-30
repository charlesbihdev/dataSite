import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { friendlyDate } from '@/lib/format';
import type { AgentNotification } from '@/types/notifications';

/** Full text of one admin notification, opened from the bell list. */
export function NotificationDialog({ notification, onClose }: { notification: AgentNotification | null; onClose: () => void }) {
    return (
        <Dialog open={notification !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                {notification ? (
                    <>
                        <DialogHeader>
                            <DialogTitle>{notification.title}</DialogTitle>
                            <DialogDescription>{friendlyDate(notification.createdAt)}</DialogDescription>
                        </DialogHeader>
                        <p className="text-sm whitespace-pre-line text-foreground">{notification.body}</p>
                    </>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
