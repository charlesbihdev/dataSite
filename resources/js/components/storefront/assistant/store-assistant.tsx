import { useState } from "react";
import { Bot } from "lucide-react";
import { AssistantPanel } from "./assistant-panel";
import { useStoreAssistant } from "@/hooks/use-store-assistant";

export interface AssistantProps {
    /** Server-built POST endpoint for this store's chat turn (same surface/host). */
    postUrl: string;
    /** Quick-start questions shown as tappable chips. */
    presets: string[];
}

/**
 * The floating storefront assistant, pinned BOTTOM-RIGHT on every shop. Closed, it's a single brand FAB;
 * open, it swaps to the chat panel. It knows only the store's public facts (served context) and refuses
 * anything off-topic with one canned line — so it can never leak the platform or a path up the ladder.
 */
export function StoreAssistant({
    storeName,
    assistant,
}: {
    storeName: string;
    assistant: AssistantProps;
}) {
    const [open, setOpen] = useState(false);
    const { messages, loading, send, clear } = useStoreAssistant(
        assistant.postUrl,
    );

    return (
        <div className="fixed right-4 bottom-4 z-50 flex flex-col items-end print:hidden">
            {open ? (
                <AssistantPanel
                    storeName={storeName}
                    presets={assistant.presets}
                    messages={messages}
                    loading={loading}
                    onSend={send}
                    onClose={() => setOpen(false)}
                    onClear={clear}
                />
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    aria-label="Open chat assistant"
                    className="flex items-center gap-2 rounded-full bg-brand py-3 pr-4 pl-3.5 text-sm font-semibold text-brand-fg shadow-lg transition hover:bg-brand-hover"
                >
                    <Bot className="size-5" />
                    <span className="hidden sm:inline">Need help?</span>
                </button>
            )}
        </div>
    );
}
