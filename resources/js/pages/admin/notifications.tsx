import { Head, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { destroy, store } from '@/actions/App/Http/Controllers/Admin/NotificationsController';
import { PageHeader } from '@/components/common/page-header';
import { Pagination, type PageLink } from '@/components/common/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface SentNotification {
    id: number;
    title: string;
    body: string;
    readCount: number;
    createdAt: string | null;
}

interface Props {
    sent: { data: SentNotification[]; links: PageLink[] };
    agentCount: number;
}

export default function AdminNotifications({ sent, agentCount }: Props) {
    const form = useForm({ title: '', body: '' });

    const remove = (n: SentNotification) => {
        if (window.confirm(`Delete "${n.title}"? Agents will no longer see it.`)) {
            router.delete(destroy(n.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Admin — Notifications" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Notifications"
                    description="Send a message to every agent. It appears as a banner on their dashboard and in the bell at the top right."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">New notification</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-2xl space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(store().url, { preserveScroll: true, onSuccess: () => form.reset() });
                            }}
                        >
                            <div className="space-y-1.5">
                                <Label htmlFor="notification-title">Title</Label>
                                <Input
                                    id="notification-title"
                                    maxLength={120}
                                    placeholder="e.g. MTN bundles back in stock"
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                />
                                {form.errors.title ? <p className="text-xs text-danger">{form.errors.title}</p> : null}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="notification-body">Message</Label>
                                <Textarea
                                    id="notification-body"
                                    rows={5}
                                    maxLength={5000}
                                    value={form.data.body}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                />
                                {form.errors.body ? <p className="text-xs text-danger">{form.errors.body}</p> : null}
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                Send to all agents
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Sent</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {sent.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No notifications sent yet.</p>
                        ) : (
                            sent.data.map((n) => (
                                <div key={n.id} className="flex gap-3 rounded-lg border border-border p-4">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium">{n.title}</p>
                                        <p className="mt-1 line-clamp-3 text-sm whitespace-pre-line text-muted-foreground">{n.body}</p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {n.createdAt} · Read by {n.readCount} of {agentCount} agents
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Delete ${n.title}`}
                                        className="shrink-0 text-danger hover:text-danger"
                                        onClick={() => remove(n)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))
                        )}
                        <Pagination links={sent.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
