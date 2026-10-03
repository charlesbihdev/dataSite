import { useCallback, useEffect, useRef, useState } from "react";
import { xsrfToken } from "@/lib/csrf";

export interface ChatMessage {
    role: "user" | "assistant";
    content: string;
}

/** Mirrors StoreAssistantService::HISTORY_LIMIT — how many prior turns we remember and send. */
const HISTORY_LIMIT = 15;

/**
 * Drives one storefront chat: keeps the short history (persisted per-store in localStorage, capped at
 * HISTORY_LIMIT so it matches what the server remembers), posts a turn to the JSON endpoint, and exposes
 * the thread + loading/error state to the widget. Guests need no account — the browser is the memory.
 */
export function useStoreAssistant(postUrl: string) {
    const storageKey = `storefront-assistant:${postUrl}`;
    const [messages, setMessages] = useState<ChatMessage[]>(() =>
        load(storageKey),
    );
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const inFlight = useRef(false);

    useEffect(() => {
        try {
            window.localStorage.setItem(
                storageKey,
                JSON.stringify(messages.slice(-HISTORY_LIMIT)),
            );
        } catch {
            // Storage can be unavailable (private mode / quota) — the chat still works in-memory.
        }
    }, [messages, storageKey]);

    const send = useCallback(
        async (text: string) => {
            const message = text.trim();
            if (message === "" || inFlight.current) return;

            inFlight.current = true;
            setError(false);
            setLoading(true);

            const history = messages.slice(-HISTORY_LIMIT);
            setMessages((prev) => [
                ...prev,
                { role: "user", content: message },
            ]);

            try {
                const res = await fetch(postUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-XSRF-TOKEN": xsrfToken(),
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({ message, history }),
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data: { reply?: string } = await res.json();
                const reply = (data.reply ?? "").trim();

                setMessages((prev) => [
                    ...prev,
                    {
                        role: "assistant",
                        content:
                            reply ||
                            "Sorry, something went wrong. Please try again.",
                    },
                ]);
            } catch {
                setError(true);
                setMessages((prev) => [
                    ...prev,
                    {
                        role: "assistant",
                        content:
                            "Sorry, I couldn't reach support just now. Please try again in a moment.",
                    },
                ]);
            } finally {
                setLoading(false);
                inFlight.current = false;
            }
        },
        [messages, postUrl],
    );

    const clear = useCallback(() => {
        setMessages([]);
        setError(false);
    }, []);

    return { messages, loading, error, send, clear };
}

function load(key: string): ChatMessage[] {
    try {
        const raw = window.localStorage.getItem(key);
        if (!raw) return [];
        const parsed: unknown = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];

        return parsed
            .filter(
                (m): m is ChatMessage =>
                    typeof m === "object" &&
                    m !== null &&
                    (m as ChatMessage).role !== undefined &&
                    ["user", "assistant"].includes((m as ChatMessage).role) &&
                    typeof (m as ChatMessage).content === "string",
            )
            .slice(-HISTORY_LIMIT);
    } catch {
        return [];
    }
}
