import { Head, useForm } from '@inertiajs/react';
import { updateConnection } from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { Column, DataTable } from '@/components/common/data-table';
import { PageHeader } from '@/components/common/page-header';
import { StatusBadge } from '@/components/common/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Admin {
    id: number;
    name: string;
    email: string | null;
    status: string;
}

interface Props {
    connection: { baseUrl: string; isActive: boolean; hasKey: boolean };
    admins: Admin[];
}

export default function AdminSettings({ connection, admins }: Props) {
    const form = useForm({
        base_url: connection.baseUrl,
        api_key: '',
        is_active: connection.isActive,
    });

    const adminColumns: Column<Admin>[] = [
        { key: 'name', header: 'Name', render: (a) => <span className="font-medium">{a.name}</span> },
        { key: 'email', header: 'Email', render: (a) => <span className="text-muted-foreground">{a.email ?? '—'}</span> },
        { key: 'status', header: 'Status', render: (a) => <StatusBadge status={a.status} /> },
    ];

    return (
        <>
            <Head title="Admin — Settings" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader title="Settings" description="Platform configuration and the Databundleshub connection." />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Databundleshub connection</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Our fulfillment pipe. Connection only — no prices are stored here.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-lg space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put(updateConnection().url, { preserveScroll: true, onSuccess: () => form.setData('api_key', '') });
                            }}
                        >
                            <div className="space-y-1.5">
                                <Label>API base URL</Label>
                                <Input
                                    value={form.data.base_url}
                                    onChange={(e) => form.setData('base_url', e.target.value)}
                                    placeholder="https://databundleshub.test/api"
                                />
                                {form.errors.base_url ? <p className="text-xs text-danger">{form.errors.base_url}</p> : null}
                            </div>

                            <div className="space-y-1.5">
                                <Label>API key</Label>
                                <Input
                                    type="password"
                                    value={form.data.api_key}
                                    onChange={(e) => form.setData('api_key', e.target.value)}
                                    placeholder={connection.hasKey ? '•••••••• (leave blank to keep)' : 'Enter API key'}
                                />
                                {form.errors.api_key ? <p className="text-xs text-danger">{form.errors.api_key}</p> : null}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                    className="size-4 rounded border-border accent-brand"
                                />
                                Active
                            </label>

                            <Button type="submit" disabled={form.processing}>
                                Save connection
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    <h2 className="text-base font-semibold">Admins</h2>
                    <DataTable columns={adminColumns} rows={admins} rowKey={(a) => a.id} emptyMessage="No admin accounts yet." />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">IP allowlist</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            The backoffice will be locked to an IP allowlist once multi-guard admin auth is wired.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
