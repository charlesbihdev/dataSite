import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { StatTile } from "@/components/common/stat-tile";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cedis } from "@/lib/format";

interface DailyOrder {
    date: string;
    total_orders: number;
    completed_orders: number;
    failed_orders: number;
    revenue: number;
}

interface DailyTopup {
    date: string;
    amount: number;
    count: number;
}

interface TopAgent {
    agent: string;
    email: string;
    orders: number;
    revenue: number;
}

interface Props {
    filters: { date_from: string; date_to: string };
    dataServed: { gb: number; orders: number; revenue: number };
    dailyOrders: DailyOrder[];
    dailyTopups: DailyTopup[];
    topAgents: TopAgent[];
}

export default function AdminAnalytics({
    filters,
    dataServed,
    dailyOrders,
    dailyTopups,
    topAgents,
}: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            "/admin/analytics",
            { date_from: dateFrom, date_to: dateTo },
            { preserveState: true },
        );
    };

    const agentColumns: Column<TopAgent>[] = [
        {
            key: "agent",
            header: "Agent",
            render: (a) => (
                <div>
                    <div className="font-medium">{a.agent}</div>
                    <div className="text-xs text-muted-foreground">
                        {a.email}
                    </div>
                </div>
            ),
        },
        { key: "orders", header: "Delivered Orders", align: "right" },
        {
            key: "revenue",
            header: "Revenue",
            align: "right",
            render: (a) => cedis(a.revenue),
        },
    ];

    const orderColumns: Column<DailyOrder>[] = [
        {
            key: "date",
            header: "Date",
            render: (o) => <span className="whitespace-nowrap">{o.date}</span>,
        },
        { key: "total_orders", header: "Total", align: "right" },
        {
            key: "completed_orders",
            header: "Delivered",
            align: "right",
            render: (o) => (
                <span className="text-success">{o.completed_orders}</span>
            ),
        },
        {
            key: "failed_orders",
            header: "Failed",
            align: "right",
            render: (o) => (
                <span className="text-danger">{o.failed_orders}</span>
            ),
        },
        {
            key: "revenue",
            header: "Revenue",
            align: "right",
            render: (o) => cedis(o.revenue),
        },
    ];

    const topupColumns: Column<DailyTopup>[] = [
        {
            key: "date",
            header: "Date",
            render: (t) => <span className="whitespace-nowrap">{t.date}</span>,
        },
        { key: "count", header: "Transactions", align: "right" },
        {
            key: "amount",
            header: "Amount",
            align: "right",
            render: (t) => cedis(t.amount),
        },
    ];

    return (
        <>
            <Head title="Admin — Analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <PageHeader
                        title="Financial Analytics"
                        description="Time-series data and leaderboards."
                    />
                    <form
                        onSubmit={applyFilters}
                        className="flex items-center gap-2"
                    >
                        <Input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="w-36"
                        />
                        <span className="text-sm text-muted-foreground">
                            to
                        </span>
                        <Input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="w-36"
                        />
                        <Button type="submit" variant="secondary">
                            Filter
                        </Button>
                    </form>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatTile
                        label="Data Served"
                        value={`${dataServed.gb.toFixed(2)} GB`}
                        hint="Total capacity delivered"
                    />
                    <StatTile
                        label="Orders Delivered"
                        value={String(dataServed.orders)}
                        hint="Successfully processed"
                    />
                    <StatTile
                        label="Revenue"
                        value={cedis(dataServed.revenue)}
                        hint="From delivered orders"
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card className="flex flex-col">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Daily Orders
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex-1">
                            <DataTable
                                columns={orderColumns}
                                rows={dailyOrders}
                                rowKey={(o) => o.date}
                                emptyMessage="No orders in this period."
                            />
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Top Agents
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <DataTable
                                    columns={agentColumns}
                                    rows={topAgents}
                                    rowKey={(a) => a.email}
                                    emptyMessage="No active agents in this period."
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Wallet Top-ups
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <DataTable
                                    columns={topupColumns}
                                    rows={dailyTopups}
                                    rowKey={(t) => t.date}
                                    emptyMessage="No top-ups in this period."
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
