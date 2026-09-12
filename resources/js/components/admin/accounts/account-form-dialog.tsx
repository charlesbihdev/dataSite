import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { store } from '@/actions/App/Http/Controllers/Admin/AccountsController';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { AccountType, Named } from './types';

// Create dialog for an agent or subagent. Owns its own form; the parent link switches with the
// active tab (agents pick a pricing tier, subagents pick an owning agent).
export function AccountFormDialog({
    open,
    onOpenChange,
    type,
    tiers,
    agents,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    type: AccountType;
    tiers: Named[];
    agents: Named[];
}) {
    const form = useForm({
        name: '',
        phone: '',
        email: '',
        username: '',
        password: '',
        pricing_tier_id: '',
        agent_id: '',
        initial_balance: '',
        is_active: true,
    });
    const errors = form.errors as Record<string, string>;
    const isSubagent = type === 'subagents';

    const submit = () => {
        form.post(store({ type }).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>New {isSubagent ? 'subagent' : 'agent'}</DialogTitle>
                </DialogHeader>

                <form
                    id="account-form"
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                >
                    <Field label="Full name" error={errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>

                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Phone" error={errors.phone}>
                            <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label="Username (optional)" error={errors.username}>
                            <Input value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                        </Field>
                    </div>

                    <Field label="Email (optional)" error={errors.email}>
                        <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    </Field>

                    {isSubagent ? (
                        <Field label="Owning agent" error={errors.agent_id}>
                            <Picker
                                placeholder="Select agent"
                                value={form.data.agent_id}
                                options={agents}
                                onChange={(v) => form.setData('agent_id', v)}
                            />
                        </Field>
                    ) : (
                        <Field label="Pricing tier" error={errors.pricing_tier_id}>
                            <Picker
                                placeholder="Select tier"
                                value={form.data.pricing_tier_id}
                                options={tiers}
                                onChange={(v) => form.setData('pricing_tier_id', v)}
                            />
                        </Field>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Temporary password" error={errors.password}>
                            <Input
                                type="text"
                                autoComplete="off"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </Field>
                        <Field label="Opening balance (GHS)" error={errors.initial_balance}>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                value={form.data.initial_balance}
                                onChange={(e) => form.setData('initial_balance', e.target.value)}
                            />
                        </Field>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="size-4 rounded border-border accent-brand"
                        />
                        Active immediately
                    </label>
                </form>

                <DialogFooter>
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="submit" form="account-form" disabled={form.processing}>
                        Create
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            {error ? <p className="text-xs text-danger">{error}</p> : null}
        </div>
    );
}

function Picker({
    placeholder,
    value,
    options,
    onChange,
}: {
    placeholder: string;
    value: string;
    options: Named[];
    onChange: (v: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((o) => (
                    <SelectItem key={o.id} value={String(o.id)}>
                        {o.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
