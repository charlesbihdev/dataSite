import { Head, Link, usePage } from "@inertiajs/react";
import { ArrowRight, Banknote, ShieldCheck, Store, Wallet, Zap } from "lucide-react";
import { login, register } from "@/routes";
import { dashboard } from "@/routes/agent";
import { Button } from "@/components/ui/button";

const appName = import.meta.env.VITE_APP_NAME || "DataSite";

const FEATURES = [
    { icon: Wallet, title: "Wholesale prices", body: "Buy every bundle at agent rates and set your own selling price on each package." },
    { icon: Store, title: "Your own storefront", body: "Get a shareable shop link where customers buy data directly, no app or sign-up needed." },
    { icon: Banknote, title: "Fast payouts", body: "Your commission matures into a withdrawable balance you cash out to MoMo or bank." },
    { icon: Zap, title: "Instant delivery", body: "Orders are fulfilled automatically the moment payment clears, around the clock." },
    { icon: ShieldCheck, title: "Secure payments", body: "Customers pay through trusted gateways; you never handle their card or MoMo details." },
];

const STEPS = [
    { n: "1", title: "Create your account", body: "Register as an agent in minutes, free." },
    { n: "2", title: "Set your prices", body: "Pick your packages and your markup." },
    { n: "3", title: "Share & earn", body: "Send your store link and earn on every sale." },
];

/**
 * D1 (admin_agents) domain root — the public front door of the platform. It pitches becoming an
 * agent (the top rung of the ladder, D1-only) and routes visitors to register/login, or straight to
 * their dashboard if already signed in. This is the ONLY marketing/recruitment surface; the store
 * domains (D2/D3) never carry a landing like this.
 */
export default function AgentLanding() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={`${appName} — Sell data, earn on every bundle`} />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6">
                        <span className="flex items-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-lg bg-brand text-sm font-bold text-brand-fg">
                                {appName.charAt(0)}
                            </span>
                            <span className="text-base font-semibold">{appName}</span>
                        </span>
                        <nav className="flex items-center gap-2 sm:gap-3">
                            {auth.user ? (
                                <Button asChild className="bg-brand text-brand-fg hover:bg-brand-hover">
                                    <Link href={dashboard()}>Go to dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost">
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    <Button asChild className="bg-brand text-brand-fg hover:bg-brand-hover">
                                        <Link href={register()}>Become an agent</Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-6xl flex-1 px-4 sm:px-6">
                    {/* Hero */}
                    <section className="py-16 text-center sm:py-24">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-brand">
                            Start earning today
                        </p>
                        <h1 className="mx-auto mt-4 max-w-3xl bg-linear-to-br from-brand to-brand/60 bg-clip-text text-4xl font-bold tracking-tight text-transparent sm:text-6xl">
                            Sell data. Earn on every bundle.
                        </h1>
                        <p className="mx-auto mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
                            Start your own data reselling business in minutes. Wholesale prices, your
                            own storefront, and payouts you control.
                        </p>
                        {!auth.user && (
                            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Button asChild size="lg" className="w-full bg-brand text-brand-fg hover:bg-brand-hover sm:w-auto">
                                    <Link href={register()}>
                                        Become an agent <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="w-full sm:w-auto">
                                    <Link href={login()}>I already have an account</Link>
                                </Button>
                            </div>
                        )}
                    </section>

                    {/* Features */}
                    <section className="grid gap-4 pb-16 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map((f) => (
                            <div key={f.title} className="rounded-2xl border border-border bg-card p-6">
                                <span className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                    <f.icon className="size-5 text-brand" />
                                </span>
                                <h3 className="mt-4 text-base font-semibold">{f.title}</h3>
                                <p className="mt-1.5 text-sm text-muted-foreground">{f.body}</p>
                            </div>
                        ))}
                    </section>

                    {/* How it works */}
                    <section className="pb-20">
                        <h2 className="text-center text-2xl font-bold tracking-tight">How it works</h2>
                        <div className="mt-8 grid gap-4 sm:grid-cols-3">
                            {STEPS.map((s) => (
                                <div key={s.n} className="rounded-2xl border border-border bg-card p-6 text-center">
                                    <span className="mx-auto flex size-10 items-center justify-center rounded-full bg-brand text-base font-bold text-brand-fg">
                                        {s.n}
                                    </span>
                                    <h3 className="mt-4 text-base font-semibold">{s.title}</h3>
                                    <p className="mt-1.5 text-sm text-muted-foreground">{s.body}</p>
                                </div>
                            ))}
                        </div>
                        {!auth.user && (
                            <div className="mt-10 text-center">
                                <Button asChild size="lg" className="bg-brand text-brand-fg hover:bg-brand-hover">
                                    <Link href={register()}>
                                        Get started free <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </section>
                </main>

                <footer className="border-t border-border py-8 text-center text-xs text-muted-foreground">
                    &copy; {new Date().getFullYear()} All rights reserved.
                </footer>
            </div>
        </>
    );
}
