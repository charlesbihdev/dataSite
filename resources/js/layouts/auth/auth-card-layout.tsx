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

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10 bg-white dark:bg-zinc-950">
            <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSJub25lIiBzdHJva2U9IiNlNWU3ZWIiIHN0cm9rZS13aWR0aD0iMC41IiBvcGFjaXR5PSIwLjUiLz48L3N2Zz4=')] dark:bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSJub25lIiBzdHJva2U9IiMzZjNmNDYiIHN0cm9rZS13aWR0aD0iMC41IiBvcGFjaXR5PSIwLjMiLz48L3N2Zz4=')] bg-[size:40px_40px] pointer-events-none" />

            <div className="absolute inset-0 bg-gradient-to-t from-white via-white/80 to-transparent dark:from-zinc-950 dark:via-zinc-950/80 pointer-events-none" />

            <div className="relative z-10 flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="flex items-center gap-2 self-center font-medium group"
                >
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-zinc-200/50 bg-white/50 shadow-sm backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50 transition-transform group-hover:scale-105">
                        <AppLogoIcon className="size-7 text-red-500" />
                    </div>
                </Link>

                <div className="flex flex-col gap-6">
                    <Card className="rounded-2xl border-zinc-200/50 shadow-xl shadow-zinc-200/20 dark:border-zinc-800 dark:shadow-none bg-white/80 dark:bg-zinc-900/80 backdrop-blur-xl">
                        <CardHeader className="px-10 pt-10 pb-2 text-center">
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
