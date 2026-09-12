import { useForm } from '@inertiajs/react';
import { addFunds } from '@/actions/App/Http/Controllers/Admin/AccountsController';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cedis } from '@/lib/format';
import type { Account, AccountType } from './types';

// Manual deposit-wallet adjustment. Positive tops up, negative deducts (same as DBH's payment).
export function AccountFundsDialog({
    account,
    type,
    onClose,
}: {
    account: Account | null;
    type: AccountType;
    onClose: () => void;
}) {
    const form = useForm({ amount: '', note: '' });
    const errors = form.errors as Record<string, string>;

    const submit = () => {
        if (!account) return;
        form.post(addFunds({ type, id: account.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Dialog open={account !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Adjust wallet — {account?.name}</DialogTitle>
                </DialogHeader>

                <form
                    id="funds-form"
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                >
                    <p className="text-sm text-muted-foreground">
                        Current balance: <span className="font-medium text-foreground">{cedis(account?.wallet ?? 0)}</span>
                    </p>

                    <div className="space-y-1.5">
                        <Label>Amount (GHS)</Label>
                        <Input
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">Positive adds credit; negative deducts.</p>
                        {errors.amount ? <p className="text-xs text-danger">{errors.amount}</p> : null}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Note (optional)</Label>
                        <Input value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                        {errors.note ? <p className="text-xs text-danger">{errors.note}</p> : null}
                    </div>
                </form>

                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="funds-form" disabled={form.processing}>
                        Apply
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
