import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import { ArrowUpRight, Check, Copy, ShoppingBag, Store, TrendingUp, Users, Wallet } from "lucide-react";
import { Column, DataTable } from "@/components/common/data-table";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cedis } from "@/lib/format";
import { storeLink, withdrawals } from "@/routes/subagent";

interface RecentSale {
    id: number;
    reference: string;
    network: string;
    capacityGb: number;
    beneficiaryPhone: string;
    customerPrice: number;
    status: string;
    createdAt: string | null;
}
interface TopSeller {
    network: string;
    capacityGb: number;
    sales: number;
    revenue: number;
}
interface Props {
    greeting: { name: string; resellerOf: string | null };
    earnings: { available: number; today: number; lifetime: number; pending: number; trend: number[] };
    store: { url: string; active: boolean; hasLink: boolean };
    thisWeek: { salesVolume: number; revenue: number; margin: number; uniqueBuyers: number };
    recentSales: RecentSale[];
    topSellers: TopSeller[];
}

function greetingWord(): string {
    const h = new Date().getHours();
    if (h < 12) return "Good morning";
    if (h < 17) return "Good afternoon";
    return "Good evening";
}

function Sparkline({ data }: { data: number[] }) {
    if (data.length < 2 || data.every((v) => v === 0)) {
        return <div className="h-8 w-full border-b border-dashed border-border" />;
    }
    const max = Math.max(...data, 1);
    const pts = data
        .map((v, i) => `${(i / (data.length - 1)) * 100},${30 - (v / max) * 28}`)
        .join(" ");
    return (
        <svg viewBox="0 0 100 30" preserveAspectRatio="none" className="h-8 w-full text-success">
            <polyline points={pts} fill="none" stroke="currentColor" strokeWidth="1.5" vectorEffect="non-scaling-stroke" />
        </svg>
    );
}

const saleColumns: Column<RecentSale>[] = [
    { key: "bundle", header: "Bundle", render: (s) => <span className="uppercase">{s.network} {s.capacityGb}GB</span> },
    { key: "phone", header: "Beneficiary", render: (s) => <span className="font-mono text-xs">{s.beneficiaryPhone}</span> },
    { key: "amount", header: "Amount", align: "right", render: (s) => cedis(s.customerPrice) },
    { key: "status", header: "Status", render: (s) => <StatusBadge status={s.status} /> },
    { key: "when", header: "When", render: (s) => <span className="text-muted-foreground">{s.createdAt ?? "—"}</span> },
];

export default function SubagentDashboard({ greeting, earnings, store, thisWeek, recentSales, topSellers }: Props) {
    const [copied, setCopied] = useState(false);
    const today = new Date().toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long", year: "numeric" });

    const copy = async () => {
        await navigator.clipboard.writeText(store.url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                {/* Greeting + actions */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{greetingWord()}, {greeting.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {today}
                            {greeting.resellerOf ? <> · Sub-agent of <span className="font-medium text-foreground">{greeting.resellerOf}</span></> : null}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild size="sm">
                            <Link href={withdrawals.url()}>
                                <ArrowUpRight className="size-4" /> Withdraw
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Available to withdraw + storefront */}
                <div className="grid items-stretch gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">Available to withdraw</CardTitle>
                            <span className="text-xs text-muted-foreground">Last 7 days</span>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-3xl font-bold tabular-nums">{cedis(earnings.available)}</p>
                                <p className="mt-1 text-xs text-muted-foreground">Pending {cedis(earnings.pending)}</p>
                            </div>
                            <Sparkline data={earnings.trend} />
                            <div className="grid grid-cols-2 gap-4 border-t border-border pt-4">
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Today earned</p>
                                    <p className="text-lg font-semibold tabular-nums">{cedis(earnings.today)}</p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Lifetime earned</p>
                                    <p className="text-lg font-semibold tabular-nums">{cedis(earnings.lifetime)}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Your storefront</CardTitle>
                        </CardHeader>
                        <CardContent className="flex h-full flex-col">
                            <div className="flex items-center gap-2">
                                <Store className="size-4 text-muted-foreground" />
                                <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${store.active ? "bg-success/10 text-success" : "bg-muted text-muted-foreground"}`}>
                                    <span className={`size-1.5 rounded-full ${store.active ? "bg-success" : "bg-muted-foreground"}`} />
                                    {store.active ? "Store live" : "Store off"}
                                </span>
                            </div>
                            <p className="mt-3 break-all rounded-lg bg-muted px-3 py-2 font-mono text-xs">{store.url}</p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" onClick={copy}>
                                    {copied ? <Check className="size-4 text-success" /> : <Copy className="size-4" />}
                                    {copied ? "Copied" : "Copy link"}
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <a href={store.url} target="_blank" rel="noreferrer">
                                        <ArrowUpRight className="size-4" /> Visit store
                                    </a>
                                </Button>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={storeLink.url()}>Manage store</Link>
                                </Button>
                            </div>
                            <p className="mt-auto pt-3 text-xs text-muted-foreground">Share your link — customers buy directly, you earn your margin on every sale.</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent sales */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent sales</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <DataTable
                            columns={saleColumns}
                            rows={recentSales}
                            rowKey={(s) => s.id}
                            emptyMessage="No sales yet. Share your store link to start earning."
                        />
                    </CardContent>
                </Card>

                {/* Top sellers + this week */}
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Top sellers · last 7 days</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {topSellers.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">Your top-selling packages will appear here after your first few sales.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {topSellers.map((t) => (
                                        <li key={`${t.network}-${t.capacityGb}`} className="flex items-center justify-between text-sm">
                                            <span className="font-medium uppercase">{t.network} {t.capacityGb}GB</span>
                                            <span className="text-muted-foreground">{t.sales} {t.sales === 1 ? "sale" : "sales"} · {cedis(t.revenue)}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">This week</CardTitle>
                            <span className="text-xs text-muted-foreground">7-day summary</span>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-6">
                            <Metric icon={ShoppingBag} label="Sales volume" value={String(thisWeek.salesVolume)} />
                            <Metric icon={TrendingUp} label="Revenue" value={cedis(thisWeek.revenue)} />
                            <Metric icon={Wallet} label="Your margin" value={cedis(thisWeek.margin)} />
                            <Metric icon={Users} label="Unique buyers" value={String(thisWeek.uniqueBuyers)} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Metric({ icon: Icon, label, value }: { icon: typeof Wallet; label: string; value: string }) {
    return (
        <div>
            <p className="flex items-center gap-1.5 text-xs uppercase tracking-wide text-muted-foreground">
                <Icon className="size-3.5" /> {label}
            </p>
            <p className="mt-1 text-xl font-bold tabular-nums">{value}</p>
        </div>
    );
}

SubagentDashboard.layout = {
    breadcrumbs: [{ title: "Dashboard", href: "/dashboard" }],
};
