import { router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { destroy, resetPassword, toggle } from '@/actions/App/Http/Controllers/Admin/AccountsController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Account, AccountType } from './types';

// Per-row action menu. Funds/details open dialogs owned by the page; the rest post inline.
export function AccountRowActions({
    account,
    type,
    onFund,
    onView,
}: {
    account: Account;
    type: AccountType;
    onFund: (a: Account) => void;
    onView: (a: Account) => void;
}) {
    const post = (url: string) => router.post(url, {}, { preserveScroll: true });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Actions">
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => onFund(account)}>Add / deduct funds</DropdownMenuItem>
                <DropdownMenuItem onClick={() => onView(account)}>View details</DropdownMenuItem>
                <DropdownMenuItem onClick={() => post(resetPassword({ type, id: account.id }).url)}>
                    Reset password
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => post(toggle({ type, id: account.id }).url)}>
                    {account.status === 'active' ? 'Suspend' : 'Activate'}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    disabled={!account.canDelete}
                    onClick={() => {
                        if (!account.canDelete) return;
                        if (window.confirm(`Delete ${account.name}?`)) {
                            router.delete(destroy({ type, id: account.id }).url, { preserveScroll: true });
                        }
                    }}
                >
                    Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
