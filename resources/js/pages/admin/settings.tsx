import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    sendTestEmail,
    updateConnection,
    updateEmail,
    updateRegistration,
} from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { Column, DataTable } from '@/components/common/data-table';
import { PageHeader } from '@/components/common/page-header';
import { StatusBadge } from '@/components/common/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Admin {
    id: number;
    name: string;
    email: string | null;
    status: string;
}

interface EmailCfg {
    fromEmail: string;
    fromName: string;
    smtpEnabled: boolean;
    smtpHost: string;
    smtpPort: number;
    smtpUsername: string;
    smtpEncryption: string;
    isActive: boolean;
    hasPassword: boolean;
}

interface Props {
    connection: { baseUrl: string; isActive: boolean; hasKey: boolean };
    email: EmailCfg;
    registration: { fee: number; isEnabled: boolean };
    admins: Admin[];
}

export default function AdminSettings({ connection, email, registration, admins }: Props) {
    const form = useForm({
        base_url: connection.baseUrl,
        api_key: '',
        is_active: connection.isActive,
    });

    const emailForm = useForm({
        from_email: email.fromEmail,
        from_name: email.fromName,
        smtp_enabled: email.smtpEnabled,
        smtp_host: email.smtpHost,
        smtp_port: email.smtpPort,
        smtp_username: email.smtpUsername,
        smtp_password: '',
        smtp_encryption: email.smtpEncryption,
        is_active: email.isActive,
    });

    const registrationForm = useForm({
        registration_fee: registration.fee,
        is_enabled: registration.isEnabled,
    });

    const testForm = useForm({ email: '' });
    const [showTest, setShowTest] = useState(false);

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
                        <CardTitle className="text-base">Email</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            SMTP account used for all outgoing mail (notifications). Stored here, not in the environment.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-lg space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                emailForm.put(updateEmail().url, { preserveScroll: true, onSuccess: () => emailForm.setData('smtp_password', '') });
                            }}
                        >
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label>From email</Label>
                                    <Input value={emailForm.data.from_email} onChange={(e) => emailForm.setData('from_email', e.target.value)} placeholder="noreply@datasite.gh" />
                                    {emailForm.errors.from_email ? <p className="text-xs text-danger">{emailForm.errors.from_email}</p> : null}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>From name</Label>
                                    <Input value={emailForm.data.from_name} onChange={(e) => emailForm.setData('from_name', e.target.value)} placeholder="DataSite" />
                                </div>
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={emailForm.data.smtp_enabled} onChange={(e) => emailForm.setData('smtp_enabled', e.target.checked)} className="size-4 rounded border-border accent-brand" />
                                Use custom SMTP (off = use the app default mailer)
                            </label>

                            {emailForm.data.smtp_enabled ? (
                                <div className="space-y-4 rounded-lg border border-border p-4">
                                    <div className="grid grid-cols-3 gap-3">
                                        <div className="col-span-2 space-y-1.5">
                                            <Label>SMTP host</Label>
                                            <Input value={emailForm.data.smtp_host} onChange={(e) => emailForm.setData('smtp_host', e.target.value)} placeholder="smtp.mailgun.org" />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Port</Label>
                                            <Input type="number" value={emailForm.data.smtp_port} onChange={(e) => emailForm.setData('smtp_port', Number(e.target.value))} />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label>Username</Label>
                                            <Input value={emailForm.data.smtp_username} onChange={(e) => emailForm.setData('smtp_username', e.target.value)} />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Password</Label>
                                            <Input type="password" value={emailForm.data.smtp_password} onChange={(e) => emailForm.setData('smtp_password', e.target.value)} placeholder={email.hasPassword ? '•••••••• (leave blank to keep)' : 'SMTP password'} />
                                        </div>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Encryption</Label>
                                        <Select value={emailForm.data.smtp_encryption} onValueChange={(v) => emailForm.setData('smtp_encryption', v)}>
                                            <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="tls">TLS</SelectItem>
                                                <SelectItem value="ssl">SSL</SelectItem>
                                                <SelectItem value="none">None</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            ) : null}

                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={emailForm.data.is_active} onChange={(e) => emailForm.setData('is_active', e.target.checked)} className="size-4 rounded border-border accent-brand" />
                                Active
                            </label>

                            <div className="flex flex-wrap items-center gap-2">
                                <Button type="submit" disabled={emailForm.processing}>Save email</Button>
                                <Button type="button" variant="secondary" onClick={() => setShowTest((s) => !s)}>Send test…</Button>
                            </div>
                        </form>

                        {showTest ? (
                            <form
                                className="mt-3 flex max-w-lg items-end gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    testForm.post(sendTestEmail().url, { preserveScroll: true, onSuccess: () => testForm.reset('email') });
                                }}
                            >
                                <div className="flex-1 space-y-1.5">
                                    <Label>Send test email to</Label>
                                    <Input type="email" value={testForm.data.email} onChange={(e) => testForm.setData('email', e.target.value)} placeholder="you@example.com" />
                                    {testForm.errors.email ? <p className="text-xs text-danger">{testForm.errors.email}</p> : null}
                                </div>
                                <Button type="submit" variant="outline" disabled={testForm.processing}>Send</Button>
                            </form>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Registration</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Fee and switch for agent self-registration (used when the agent portal lands).
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="flex max-w-lg flex-wrap items-end gap-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                registrationForm.put(updateRegistration().url, { preserveScroll: true });
                            }}
                        >
                            <div className="space-y-1.5">
                                <Label>Registration fee (GHS)</Label>
                                <Input type="number" step="0.01" className="w-40" value={registrationForm.data.registration_fee} onChange={(e) => registrationForm.setData('registration_fee', Number(e.target.value))} />
                                {registrationForm.errors.registration_fee ? <p className="text-xs text-danger">{registrationForm.errors.registration_fee}</p> : null}
                            </div>
                            <label className="flex items-center gap-2 pb-2 text-sm">
                                <input type="checkbox" checked={registrationForm.data.is_enabled} onChange={(e) => registrationForm.setData('is_enabled', e.target.checked)} className="size-4 rounded border-border accent-brand" />
                                Self-registration open
                            </label>
                            <Button type="submit" disabled={registrationForm.processing}>Save</Button>
                        </form>
                    </CardContent>
                </Card>

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
