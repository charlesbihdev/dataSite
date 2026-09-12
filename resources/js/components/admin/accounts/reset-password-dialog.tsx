import { useForm } from "@inertiajs/react";
import { useEffect } from "react";
import { resetPassword } from "@/actions/App/Http/Controllers/Admin/AccountsController";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    type: string;
    accountId: number;
    accountName: string;
}

export function ResetPasswordDialog({
    open,
    onOpenChange,
    type,
    accountId,
    accountName,
}: Props) {
    const form = useForm({
        password: "",
        password_confirmation: "",
    });

    useEffect(() => {
        if (open) form.reset();
    }, [open]);

    const submit = () => {
        if (form.data.password !== form.data.password_confirmation) {
            form.setError("password_confirmation", "Passwords do not match");
            return;
        }

        form.post(resetPassword({ type, id: accountId }).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Reset Password</DialogTitle>
                    <DialogDescription>
                        Set a new password for{" "}
                        <strong className="text-foreground">
                            {accountName}
                        </strong>
                        .
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    <Field label="New password" error={form.errors.password}>
                        <Input
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData("password", e.target.value)
                            }
                        />
                    </Field>

                    <Field
                        label="Confirm password"
                        error={form.errors.password_confirmation}
                    >
                        <Input
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) => {
                                form.setData(
                                    "password_confirmation",
                                    e.target.value,
                                );
                                form.clearErrors("password_confirmation");
                            }}
                        />
                    </Field>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={form.processing}
                    >
                        Cancel
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        {form.processing ? "Saving..." : "Reset password"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <label className="text-sm font-medium">{label}</label>
            {children}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </div>
    );
}
