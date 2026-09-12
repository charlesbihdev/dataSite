import { type InertiaFormProps } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

export interface BandFormValue {
    network: string;
    min_gb: string;
    max_gb: string;
    rate: string;
    is_active: boolean;
    pricing_tier_id?: string;
}

// Shared add/edit form for a pricing band (base cost or tier rate). Presentational: the owning
// panel supplies the useForm and maps `rate` to the right server field on submit.
export function BandDialog({
    open,
    onOpenChange,
    title,
    rateLabel,
    rateErrorKey,
    form,
    onSubmit,
    tiers,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    rateLabel: string;
    rateErrorKey: string;
    form: InertiaFormProps<BandFormValue>;
    onSubmit: () => void;
    tiers?: { id: number; name: string }[];
}) {
    const errors = form.errors as Record<string, string>;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>

                <form
                    id="band-form"
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onSubmit();
                    }}
                >
                    {tiers ? (
                        <div className="space-y-1.5">
                            <Label>Tier</Label>
                            <Select
                                value={form.data.pricing_tier_id ?? ""}
                                onValueChange={(v) =>
                                    form.setData("pricing_tier_id", v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select tier" />
                                </SelectTrigger>
                                <SelectContent>
                                    {tiers.map((t) => (
                                        <SelectItem
                                            key={t.id}
                                            value={String(t.id)}
                                        >
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
                    ) : null}

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Min GB</Label>
                            <Input
                                type="number"
                                step="0.01"
                                value={form.data.min_gb}
                                onChange={(e) =>
                                    form.setData("min_gb", e.target.value)
                                }
                            />
                            {errors.min_gb ? (
                                <p className="text-xs text-danger">
                                    {errors.min_gb}
                                </p>
                            ) : null}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Max GB</Label>
                            <Input
                                type="number"
                                step="0.01"
                                value={form.data.max_gb}
                                onChange={(e) =>
                                    form.setData("max_gb", e.target.value)
                                }
                            />
                            {errors.max_gb ? (
                                <p className="text-xs text-danger">
                                    {errors.max_gb}
                                </p>
                            ) : null}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>{rateLabel}</Label>
                        <Input
                            type="number"
                            step="0.01"
                            value={form.data.rate}
                            onChange={(e) =>
                                form.setData("rate", e.target.value)
                            }
                        />
                        {errors[rateErrorKey] ? (
                            <p className="text-xs text-danger">
                                {errors[rateErrorKey]}
                            </p>
                        ) : null}
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData("is_active", e.target.checked)
                            }
                            className="size-4 rounded border-border accent-brand"
                        />
                        Active
                    </label>
                </form>

                <DialogFooter>
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="band-form"
                        disabled={form.processing}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
