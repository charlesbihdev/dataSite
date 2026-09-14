import { Head } from "@inertiajs/react";
import { LinkIcon, Ticket } from "lucide-react";

/**
 * D2 (agent_store) domain root, reached WITHOUT a store link. There is no public catalog here —
 * every shop is an individual seller's own link (/buy/{slug}) — so this page politely tells a stray
 * visitor to open the exact link they were given. Deliberately minimal and BRAND-ANONYMOUS: it names
 * no platform, exposes no signup and no portal login, keeping the URL-truncation firewall intact.
 */
export default function AgentStoreLanding() {
    return (
        <>
            <Head title="Store" />

            <div className="flex min-h-screen items-center justify-center bg-background px-4 py-12 text-foreground">
                <div className="w-full max-w-md rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
                    <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-subtle">
                        <Ticket className="size-7 text-brand" />
                    </span>
                    <h1 className="mt-5 text-2xl font-bold tracking-tight">
                        Please use the link you were given
                    </h1>
                    <p className="mx-auto mt-3 max-w-sm text-sm text-muted-foreground">
                        There's no public store on this page. To buy data, open the exact link you
                        were given.
                    </p>

                    <div className="mt-6 flex items-center justify-center gap-2 rounded-xl border border-border bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
                        <LinkIcon className="size-3.5 shrink-0" />
                        <span>Open the full link exactly as it was sent to you</span>
                    </div>
                </div>
            </div>
        </>
    );
}
