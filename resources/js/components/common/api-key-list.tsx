import { Trash2 } from "lucide-react";
import { useState } from "react";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";

export interface ApiKeyItem {
    id: number;
    name: string;
    prefix: string;
    isActive: boolean;
    lastUsedAt: string;
    createdAt: string;
}

interface ApiKeyListProps {
    keys: ApiKeyItem[];
    onToggle: (id: number) => void;
    onRevoke: (id: number) => void;
    emptyMessage?: string;
}

/**
 * Reusable list of API keys with status badges, toggle action, and revoke confirmation.
 */
export function ApiKeyList({
    keys,
    onToggle,
    onRevoke,
    emptyMessage = "No API keys yet.",
}: ApiKeyListProps) {
    const [revokeTarget, setRevokeTarget] = useState<ApiKeyItem | null>(null);

    const confirmRevoke = () => {
        if (!revokeTarget) return;
        onRevoke(revokeTarget.id);
        setRevokeTarget(null);
    };

    if (keys.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                {emptyMessage}
            </p>
        );
    }

    return (
        <>
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
                                        key.isActive ? "active" : "suspended"
                                    }
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                <code className="font-mono">
                                    {key.prefix}_…
                                </code>
                                {" · "}
                                Created {key.createdAt}
                                {key.lastUsedAt && key.lastUsedAt !== "Never"
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
                                onClick={() => onToggle(key.id)}
                            >
                                {key.isActive ? "Suspend" : "Activate"}
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7 text-danger hover:text-danger"
                                aria-label="Revoke Key"
                                onClick={() => setRevokeTarget(key)}
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </div>
                    </div>
                ))}
            </dl>

            <ConfirmDialog
                open={revokeTarget !== null}
                onOpenChange={(o) => !o && setRevokeTarget(null)}
                title="Revoke API Key"
                description={`Revoke "${revokeTarget?.name}"? Any integrated client using it will immediately lose access.`}
                confirmLabel="Revoke"
                onConfirm={confirmRevoke}
            />
        </>
    );
}
