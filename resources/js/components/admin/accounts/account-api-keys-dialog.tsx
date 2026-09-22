import { router, useForm, usePage } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useState } from "react";
import {
    destroy,
    store,
    toggle,
} from "@/actions/App/Http/Controllers/Admin/AccountApiKeysController";
import { ApiKeyList } from "@/components/common/api-key-list";
import { ApiKeyReveal } from "@/components/common/api-key-reveal";
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
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import type { Account, AccountType, RawApiKeyFlash } from "./types";

interface PageProps {
    [key: string]: unknown;
    flash?: {
        rawApiKey?: RawApiKeyFlash | null;
    };
}

export function AccountApiKeysDialog({
    account,
    type,
    onClose,
}: {
    account: Account | null;
    type: AccountType;
    onClose: () => void;
}) {
    const { props } = usePage<PageProps>();
    const rawFlash = props.flash?.rawApiKey;
    const isMatchingAccount =
        rawFlash && account && rawFlash.accountId === account.id;

    const [isMinting, setIsMinting] = useState(false);

    const { data, setData, post, processing, reset, errors } = useForm({
        name: "Default API Key",
    });

    const handleMint = (e: React.FormEvent) => {
        e.preventDefault();
        if (!account) return;

        post(store({ type, id: account.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsMinting(false);
                reset();
            },
        });
    };

    const handleToggle = (keyId: number) => {
        router.post(
            toggle({ apiKey: keyId }).url,
            {},
            { preserveScroll: true },
        );
    };

    const handleRevoke = (keyId: number) => {
        router.delete(destroy({ apiKey: keyId }).url, {
            preserveScroll: true,
        });
    };

    const keys = account?.apiKeys ?? [];

    return (
        <Dialog open={account !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="pr-6 break-words">
                        API Keys — {account?.name}
                    </DialogTitle>
                    <DialogDescription>
                        Developer credentials for automated order placement.
                    </DialogDescription>
                </DialogHeader>

                {/* One-time raw key reveal */}
                {isMatchingAccount && rawFlash ? (
                    <ApiKeyReveal rawKey={rawFlash.rawKey} />
                ) : null}

                {/* Mint form */}
                {isMinting ? (
                    <form
                        id="mint-form"
                        onSubmit={handleMint}
                        className="space-y-3"
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="key-name">Key Name</Label>
                            <Input
                                id="key-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData("name", e.target.value)
                                }
                                placeholder="e.g. Production, Mobile App"
                                autoFocus
                            />
                            {errors.name ? (
                                <p className="text-xs text-danger">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setIsMinting(false);
                                    reset();
                                }}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? "Minting…" : "Generate Key"}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : (
                    <Button
                        type="button"
                        size="sm"
                        className="gap-1.5 justify-self-start"
                        onClick={() => setIsMinting(true)}
                    >
                        <Plus className="size-3.5" />
                        Mint API Key
                    </Button>
                )}

                <Separator />

                {/* Key list */}
                <ApiKeyList
                    keys={keys}
                    onToggle={handleToggle}
                    onRevoke={handleRevoke}
                />
            </DialogContent>
        </Dialog>
    );
}
