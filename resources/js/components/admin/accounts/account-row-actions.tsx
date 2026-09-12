import { router } from "@inertiajs/react";
import { MoreHorizontal } from "lucide-react";
import { useState } from "react";
import {
    destroy,
    toggle,
} from "@/actions/App/Http/Controllers/Admin/AccountsController";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { Account, AccountType } from "./types";
import { ResetPasswordDialog } from "./reset-password-dialog";

// Per-row action menu. Funds/details/keys open dialogs owned by the page; the rest post inline.
export function AccountRowActions({
    account,
    type,
    onFund,
    onView,
    onManageKeys,
    onAssignTier,
}: {
    account: Account;
    type: AccountType;
    onFund: (a: Account) => void;
    onView: (a: Account) => void;
    onManageKeys: (a: Account) => void;
    onAssignTier?: (a: Account) => void;
}) {
    const [showDelete, setShowDelete] = useState(false);
    const [showResetPwd, setShowResetPwd] = useState(false);

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label="Actions">
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => onFund(account)}>
                        Add / deduct funds
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onView(account)}>
                        View details
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onManageKeys(account)}>
                        API keys
                    </DropdownMenuItem>
                    {type === "agents" && onAssignTier ? (
                        <DropdownMenuItem onClick={() => onAssignTier(account)}>
                            Change tier
                        </DropdownMenuItem>
                    ) : null}
                    <DropdownMenuItem onClick={() => setShowResetPwd(true)}>
                        Reset password
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() =>
                            post(toggle({ type, id: account.id }).url)
                        }
                    >
                        {account.status === "active" ? "Suspend" : "Activate"}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        disabled={!account.canDelete}
                        onClick={() => {
                            if (!account.canDelete) return;
                            setShowDelete(true);
                        }}
                    >
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <ResetPasswordDialog
                open={showResetPwd}
                onOpenChange={setShowResetPwd}
                type={type}
                accountId={account.id}
                accountName={account.name}
            />

            <ConfirmDialog
                open={showDelete}
                onOpenChange={setShowDelete}
                title={`Delete ${account.name}?`}
                description="This action cannot be undone. The account and all associated data will be permanently removed."
                onConfirm={() =>
                    router.delete(destroy({ type, id: account.id }).url, {
                        preserveScroll: true,
                        onFinish: () => setShowDelete(false),
                    })
                }
            />
        </>
    );
}
