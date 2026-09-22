import { Check, Copy } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";

/**
 * One-time raw API key reveal banner with a click-to-copy button.
 */
export function ApiKeyReveal({ rawKey }: { rawKey: string }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(rawKey);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="space-y-1.5 rounded-lg border border-warning/40 bg-warning/10 p-3">
            <p className="text-xs font-semibold text-foreground">
                New key minted — copy it now, it won't be shown again.
            </p>
            <div className="flex items-center gap-2">
                <code className="min-w-0 flex-1 rounded bg-card px-2 py-1 font-mono text-xs break-all select-all">
                    {rawKey}
                </code>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 shrink-0 gap-1.5 px-2 text-xs"
                    onClick={handleCopy}
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
    );
}
