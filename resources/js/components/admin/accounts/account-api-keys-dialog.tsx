import { useForm, router, usePage } from "@inertiajs/react";
import { Check, Copy, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import {
    destroy,
    store,
    toggle,
} from "@/actions/App/Http/Controllers/Admin/AccountApiKeysController";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { StatusBadge } from "@/components/common/status-badge";
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
    const [copied, setCopied] = useState(false);
    const [revokeTarget, setRevokeTarget] = useState<{
        id: number;
        name: string;
    } | null>(null);

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

    const handleCopy = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleToggle = (keyId: number) => {
        router.post(
            toggle({ apiKey: keyId }).url,
            {},
            { preserveScroll: true },
        );
    };

    const confirmRevoke = () => {
        if (!revokeTarget) return;
        router.delete(destroy({ apiKey: revokeTarget.id }).url, {
            preserveScroll: true,
            onFinish: () => setRevokeTarget(null),
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
                    <div className="space-y-1.5 rounded-lg border border-warning/40 bg-warning/10 p-3">
                        <p className="text-xs font-semibold text-foreground">
                            New key minted — copy it now, it won't be shown
                            again.
                        </p>
                        <div className="flex items-center gap-2">
                            <code className="min-w-0 flex-1 rounded bg-card px-2 py-1 font-mono text-xs break-all select-all">
                                {rawFlash.rawKey}
                            </code>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-7 shrink-0 gap-1.5 px-2 text-xs"
                                onClick={() => handleCopy(rawFlash.rawKey)}
                            >
                                {copied ? (
                                    <>
                                        <Check className="size-3 text-success" />
                                        Copied
                                    </>
                                ) : (
                                    <>
                                        <Copy className="size-3" />
                                        Copy
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
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
                {keys.length > 0 ? (
                    <dl className="space-y-2">
                        {keys.map((key) => (
                            <div
                                key={key.id}
                                className="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                            >
                                <div className="min-w-0 space-y-0.5">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <span className="min-w-0 truncate font-medium text-foreground">
                                            {key.name}
                                        </span>
                                        <StatusBadge
                                            status={
                                                key.isActive
                                                    ? "active"
                                                    : "suspended"
                                            }
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        <code className="font-mono">
                                            {key.prefix}_…
                                        </code>
                                        {" · "}
                                        Created {key.createdAt}
                                        {key.lastUsedAt !== "Never"
                                            ? ` · Used ${key.lastUsedAt}`
                                            : null}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => handleToggle(key.id)}
                                    >
                                        {key.isActive ? "Suspend" : "Activate"}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        className="size-7 text-danger hover:text-danger"
                                        aria-label="Revoke Key"
                                        onClick={() =>
                                            setRevokeTarget({
                                                id: key.id,
                                                name: key.name,
                                            })
                                        }
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </dl>
                ) : (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        No API keys yet.
                    </p>
                )}
            </DialogContent>

            <ConfirmDialog
                open={revokeTarget !== null}
                onOpenChange={(o) => !o && setRevokeTarget(null)}
                title="Revoke API Key"
                description={`Revoke "${revokeTarget?.name}"? Any integrated client using it will immediately lose access.`}
                confirmLabel="Revoke"
                onConfirm={confirmRevoke}
            />
        </Dialog>
    );
}
