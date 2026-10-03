import { Bot } from "lucide-react";
import { FormattedText } from "./formatted-text";
import { type ChatMessage } from "@/hooks/use-store-assistant";
import { cn } from "@/lib/utils";

/** A single chat bubble. Customer messages sit right in brand blue; assistant replies sit left in grey. */
export function AssistantMessage({ message }: { message: ChatMessage }) {
    const isUser = message.role === "user";

    return (
        <div
            className={cn(
                "flex items-end gap-2",
                isUser ? "justify-end" : "justify-start",
            )}
        >
            {!isUser && (
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-brand">
                    <Bot className="size-4" />
                </span>
            )}
            <div
                className={cn(
                    "max-w-[80%] rounded-2xl px-3.5 py-2 text-sm leading-relaxed",
                    isUser
                        ? "rounded-br-sm bg-brand whitespace-pre-wrap text-brand-fg"
                        : "rounded-bl-sm bg-muted text-foreground",
                )}
            >
                {isUser ? (
                    message.content
                ) : (
                    <FormattedText content={message.content} />
                )}
            </div>
        </div>
    );
}

/** The three-dot "assistant is typing" indicator shown while a reply is in flight. */
export function AssistantTyping() {
    return (
        <div className="flex items-end gap-2">
            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-brand">
                <Bot className="size-4" />
            </span>
            <div className="flex items-center gap-1 rounded-2xl rounded-bl-sm bg-muted px-3.5 py-3">
                <span className="size-1.5 animate-bounce rounded-full bg-muted-foreground" />
                <span className="size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:150ms]" />
                <span className="size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:300ms]" />
            </div>
        </div>
    );
}
