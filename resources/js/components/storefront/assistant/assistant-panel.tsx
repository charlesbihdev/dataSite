import { FormEvent, useEffect, useRef, useState } from "react";
import { Bot, RotateCcw, Send, X } from "lucide-react";
import { AssistantMessage, AssistantTyping } from "./assistant-message";
import { type ChatMessage } from "@/hooks/use-store-assistant";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

interface Props {
    storeName: string;
    presets: string[];
    messages: ChatMessage[];
    loading: boolean;
    onSend: (text: string) => void;
    onClose: () => void;
    onClear: () => void;
}

/**
 * The chat panel: header, scrolling thread (with a friendly welcome + tappable preset questions when
 * empty), a typing indicator, and the message box. Storefront-only, forced-light, quiet house style.
 */
export function AssistantPanel({
    storeName,
    presets,
    messages,
    loading,
    onSend,
    onClose,
    onClear,
}: Props) {
    const [draft, setDraft] = useState("");
    const scrollRef = useRef<HTMLDivElement>(null);
    const isEmpty = messages.length === 0;

    useEffect(() => {
        scrollRef.current?.scrollTo({
            top: scrollRef.current.scrollHeight,
            behavior: "smooth",
        });
    }, [messages, loading]);

    function submit(e: FormEvent) {
        e.preventDefault();
        onSend(draft);
        setDraft("");
    }

    return (
        <div className="flex h-[32rem] max-h-[calc(100vh-6rem)] w-[22rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-xl">
            <header className="flex items-center justify-between gap-2 border-b border-border bg-brand px-4 py-3 text-brand-fg">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-fg/15">
                        <Bot className="size-4.5" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold leading-tight">
                            {storeName} Assistant
                        </p>
                        <p className="truncate text-xs text-brand-fg/80">
                            Here to help you buy data
                        </p>
                    </div>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    {!isEmpty && (
                        <button
                            type="button"
                            onClick={onClear}
                            aria-label="Clear chat"
                            className="rounded-full p-1.5 transition hover:bg-brand-fg/15"
                        >
                            <RotateCcw className="size-4" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close chat"
                        className="rounded-full p-1.5 transition hover:bg-brand-fg/15"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            </header>

            <div
                ref={scrollRef}
                className="flex-1 space-y-3 overflow-y-auto px-4 py-4"
            >
                {isEmpty ? (
                    <WelcomeAndPresets
                        storeName={storeName}
                        presets={presets}
                        onPick={onSend}
                    />
                ) : (
                    messages.map((m, i) => (
                        <AssistantMessage key={i} message={m} />
                    ))
                )}
                {loading && <AssistantTyping />}
            </div>

            <form
                onSubmit={submit}
                className="flex items-center gap-2 border-t border-border bg-card p-3"
            >
                <Input
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    placeholder="Ask about buying data…"
                    maxLength={500}
                    disabled={loading}
                    aria-label="Message"
                    className="flex-1"
                />
                <Button
                    type="submit"
                    size="icon"
                    disabled={loading || draft.trim() === ""}
                    aria-label="Send"
                >
                    <Send className="size-4" />
                </Button>
            </form>
        </div>
    );
}

function WelcomeAndPresets({
    storeName,
    presets,
    onPick,
}: {
    storeName: string;
    presets: string[];
    onPick: (text: string) => void;
}) {
    return (
        <div className="space-y-4">
            <div className="rounded-2xl rounded-bl-sm bg-muted px-3.5 py-2.5 text-sm leading-relaxed text-foreground">
                Hi! 👋 I'm the {storeName} assistant. Ask me anything about
                buying data here, or tap a question below.
            </div>
            <div className="flex flex-wrap gap-2">
                {presets.map((q) => (
                    <button
                        key={q}
                        type="button"
                        onClick={() => onPick(q)}
                        className="rounded-full border border-border bg-card px-3 py-1.5 text-left text-xs font-medium text-foreground transition hover:border-brand hover:text-brand"
                    >
                        {q}
                    </button>
                ))}
            </div>
        </div>
    );
}
