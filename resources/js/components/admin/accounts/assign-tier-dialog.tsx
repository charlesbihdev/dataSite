import { useForm } from "@inertiajs/react";
import { assignTier } from "@/actions/App/Http/Controllers/Admin/AccountsController";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { Account, Named } from "./types";

export function AssignTierDialog({
    account,
    tiers,
    onClose,
}: {
    account: Account | null;
    tiers: Named[];
    onClose: () => void;
}) {
    const form = useForm({
        pricing_tier_id: account?.pricingTierId
            ? String(account.pricingTierId)
            : "",
    });
    const errors = form.errors as Record<string, string>;

    const submit = () => {
        if (!account) return;
        form.put(assignTier({ id: account.id }).url, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog
            open={account !== null}
            onOpenChange={(o) => {
                if (!o) onClose();
                else if (account) {
                    form.setData(
                        "pricing_tier_id",
                        account.pricingTierId
                            ? String(account.pricingTierId)
                            : "",
                    );
                }
            }}
        >
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Change tier — {account?.name}</DialogTitle>
                </DialogHeader>

                <form
                    id="assign-tier-form"
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                >
                    <p className="text-sm text-muted-foreground">
                        Current tier:{" "}
                        <span className="font-medium text-foreground">
                            {account?.pricingTierName ?? "None"}
                        </span>
                    </p>

                    <div className="space-y-1.5">
                        <Label>New tier</Label>
                        <Select
                            value={form.data.pricing_tier_id}
                            onValueChange={(v) =>
                                form.setData("pricing_tier_id", v)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select tier" />
                            </SelectTrigger>
                            <SelectContent>
                                {tiers.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.pricing_tier_id ? (
                            <p className="text-xs text-danger">
                                {errors.pricing_tier_id}
                            </p>
                        ) : null}
                    </div>
                </form>

                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="assign-tier-form"
                        disabled={form.processing}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
