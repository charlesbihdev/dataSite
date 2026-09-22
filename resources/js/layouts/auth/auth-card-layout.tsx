import { Link } from "@inertiajs/react";
import type { PropsWithChildren } from "react";
import AppLogoIcon from "@/components/app-logo-icon";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { home } from "@/routes";

/**
 * Which portal this auth screen belongs to. Each tier gets a slightly different look — a badge,
 * a coloured top bar, and a tinted logo — so a signer-in can tell the agent, subagent, and admin
 * doors apart at a glance. Full literal class strings so Tailwind's JIT keeps them.
 */
export type AuthAccent = "agent" | "subagent" | "admin";

const ACCENTS: Record<AuthAccent, { label: string; logo: string; ring: string; bar: string; badge: string }> = {
    agent: {
        label: "Agent Portal",
        logo: "text-blue-600",
        ring: "border-blue-200/60 dark:border-blue-900/50",
        bar: "bg-blue-500",
        badge: "bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-400/20",
    },
    subagent: {
        label: "Reseller Portal",
        logo: "text-emerald-600",
        ring: "border-emerald-200/60 dark:border-emerald-900/50",
        bar: "bg-emerald-500",
        badge: "bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-400/20",
    },
    admin: {
        label: "Administrator",
        logo: "text-red-500",
        ring: "border-red-200/60 dark:border-red-900/50",
        bar: "bg-red-500",
        badge: "bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-400/20",
    },
};

export default function AuthCardLayout({
    children,
    title,
    description,
    accent = "agent",
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
    accent?: AuthAccent;
}>) {
    const tier = ACCENTS[accent] ?? ACCENTS.agent;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10 bg-white dark:bg-zinc-950">
            <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSJub25lIiBzdHJva2U9IiNlNWU3ZWIiIHN0cm9rZS13aWR0aD0iMC41IiBvcGFjaXR5PSIwLjUiLz48L3N2Zz4=')] dark:bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSJub25lIiBzdHJva2U9IiMzZjNmNDYiIHN0cm9rZS13aWR0aD0iMC41IiBvcGFjaXR5PSIwLjMiLz48L3N2Zz4=')] bg-[size:40px_40px] pointer-events-none" />

            <div className="absolute inset-0 bg-gradient-to-t from-white via-white/80 to-transparent dark:from-zinc-950 dark:via-zinc-950/80 pointer-events-none" />

            <div className="relative z-10 flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="flex items-center gap-2 self-center font-medium group"
                >
                    <div className={`flex h-12 w-12 items-center justify-center rounded-xl border bg-white/50 shadow-sm backdrop-blur-sm dark:bg-zinc-900/50 transition-transform group-hover:scale-105 ${tier.ring}`}>
                        <AppLogoIcon className={`size-7 ${tier.logo}`} />
                    </div>
                </Link>

                <div className="flex flex-col gap-6">
                    <Card className="overflow-hidden rounded-2xl border-zinc-200/50 shadow-xl shadow-zinc-200/20 dark:border-zinc-800 dark:shadow-none bg-white/80 dark:bg-zinc-900/80 backdrop-blur-xl">
                        <div className={`h-1.5 w-full ${tier.bar}`} />
                        <CardHeader className="px-10 pt-8 pb-2 text-center">
                            <span className={`mx-auto mb-2 inline-flex w-fit items-center rounded-full px-3 py-0.5 text-xs font-medium uppercase tracking-wide ring-1 ring-inset ${tier.badge}`}>
                                {tier.label}
                            </span>
                            <CardTitle className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
                                {title}
                            </CardTitle>
                            <CardDescription className="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                                {description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">
                            {children}
                        </CardContent>
                    </Card>
                </div>

                {/* Footer attribution or extra links could go here */}
            </div>
        </div>
    );
}
