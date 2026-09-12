import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { index as accountsIndex } from "@/actions/App/Http/Controllers/Admin/AccountsController";
import { AccountAnalytics } from "@/components/admin/accounts/account-analytics";
import { AccountApiKeysDialog } from "@/components/admin/accounts/account-api-keys-dialog";
import { AccountDetailsDialog } from "@/components/admin/accounts/account-details-dialog";
import { AssignTierDialog } from "@/components/admin/accounts/assign-tier-dialog";
import { AccountFilters } from "@/components/admin/accounts/account-filters";
import { AccountFormDialog } from "@/components/admin/accounts/account-form-dialog";
import { AccountFundsDialog } from "@/components/admin/accounts/account-funds-dialog";
import { AccountRowActions } from "@/components/admin/accounts/account-row-actions";
import { AccountsToolbar } from "@/components/admin/accounts/accounts-toolbar";
import type {
    Account,
    AccountAnalytics as Analytics,
    AccountType,
    Named,
} from "@/components/admin/accounts/types";
import { Column, DataTable } from "@/components/common/data-table";
import { PageHeader } from "@/components/common/page-header";
import { StatTile } from "@/components/common/stat-tile";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { cedis } from "@/lib/format";
import { cn } from "@/lib/utils";

interface Props {
    type: AccountType;
    filters: { q: string | null; status: string | null };
    accounts: Account[];
    counts: { agents: number; subagents: number };
    stats: {
        totalAccounts: number;
        active30d: number;
        totalBalance: number;
        totalOrders: number;
        pendingOrders: number;
        totalRevenue: number;
    };
    analytics: Analytics;
    tiers: Named[];
    agents: Named[];
}

export default function AdminAccounts({
    type,
    filters,
    accounts,
    counts,
    stats,
    analytics,
    tiers,
    agents,
}: Props) {
    const [creating, setCreating] = useState(false);
    const [funding, setFunding] = useState<Account | null>(null);
    const [viewing, setViewing] = useState<Account | null>(null);
    const [managingKeys, setManagingKeys] = useState<Account | null>(null);
    const [assigningTier, setAssigningTier] = useState<Account | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const singular = type === "agents" ? "agent" : "subagent";
    const label = type === "agents" ? "Agents" : "Subagents";

    // Keep active keys dialog synced with latest account row data after actions
    const activeKeysAccount = managingKeys
        ? (accounts.find((a) => a.id === managingKeys.id) ?? managingKeys)
        : null;

    const allChecked =
        accounts.length > 0 && selected.length === accounts.length;
    const toggleAll = () =>
        setSelected(allChecked ? [] : accounts.map((a) => a.id));
    const toggleOne = (id: number) =>
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );

    const columns: Column<Account>[] = [
        {
            key: "select",
            header: (
                <input
                    type="checkbox"
                    checked={allChecked}
                    onChange={toggleAll}
                    className="size-4 accent-brand"
                />
            ),
            render: (a) => (
                <input
                    type="checkbox"
                    checked={selected.includes(a.id)}
                    onChange={() => toggleOne(a.id)}
                    className="size-4 accent-brand"
                />
            ),
        },
        {
            key: "name",
            header: "Name",
            render: (a) => (
                <div className="flex flex-col">
                    <span className="font-medium">{a.name}</span>
                    <span className="text-xs text-muted-foreground">
                        {a.email ?? a.phone}
                    </span>
                </div>
            ),
        },
        {
            key: "detail",
            header: type === "agents" ? "Tier / subagents" : "Agent",
        },
        {
            key: "orders",
            header: "Orders",
            align: "right",
            render: (a) => String(a.ordersCount),
        },
        {
            key: "wallet",
            header: "Wallet",
            align: "right",
            render: (a) => cedis(a.wallet),
        },
        {
            key: "earnings",
            header: "Earnings",
            align: "right",
            render: (a) => cedis(a.earnings),
        },
        {
            key: "lastActivity",
            header: "Last activity",
            render: (a) => (
                <span className="text-muted-foreground">
                    {a.lastActivity ?? "Never"}
                </span>
            ),
        },
        {
            key: "status",
            header: "Status",
            render: (a) => <StatusBadge status={a.status} />,
        },
        {
            key: "actions",
            header: "",
            align: "right",
            render: (a) => (
                <AccountRowActions
                    account={a}
                    type={type}
                    onFund={setFunding}
                    onView={setViewing}
                    onManageKeys={setManagingKeys}
                    onAssignTier={
                        type === "agents" ? setAssigningTier : undefined
                    }
                />
            ),
        },
    ];

    const tab = (value: AccountType, label: string, count: number) => (
        <button
            type="button"
            onClick={() => {
                setSelected([]);
                router.get(
                    accountsIndex.url(),
                    { type: value },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                );
            }}
            className={cn(
                "rounded-md px-3 py-1.5 text-sm font-medium",
                type === value
                    ? "bg-brand text-brand-fg"
                    : "text-muted-foreground hover:bg-muted",
            )}
        >
            {label} <span className="ml-1 opacity-70">{count}</span>
        </button>
    );

    return (
        <>
            <Head title="Admin — Accounts" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Accounts"
                    description="Agents and subagents — deposit wallet and withdrawable earnings."
                    actions={
                        <Button onClick={() => setCreating(true)}>
                            New {singular}
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatTile
                        label={`Total ${label}`}
                        value={String(stats.totalAccounts)}
                        hint="Registered accounts"
                    />
                    <StatTile
                        label={`Active ${label} (30d)`}
                        value={String(stats.active30d)}
                        hint="Ordered in last 30 days"
                    />
                    <StatTile
                        label="Total Balances"
                        value={cedis(stats.totalBalance)}
                        hint="Deposit wallets combined"
                    />
                    <StatTile
                        label="Total Orders"
                        value={String(stats.totalOrders)}
                        hint="All time"
                    />
                    <StatTile
                        label="Pending Orders"
                        value={String(stats.pendingOrders)}
                        hint="Still processing"
                    />
                    <StatTile
                        label="Total Revenue"
                        value={cedis(stats.totalRevenue)}
                        hint="Completed orders"
                    />
                </div>

                <AccountAnalytics type={type} analytics={analytics} />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1">
                        {tab("agents", "Agents", counts.agents)}
                        {tab("subagents", "Subagents", counts.subagents)}
                    </div>
                    <AccountFilters type={type} filters={filters} />
                </div>

                {selected.length > 0 ? (
                    <AccountsToolbar
                        type={type}
                        selectedIds={selected}
                        onDone={() => setSelected([])}
                    />
                ) : null}

                <DataTable
                    columns={columns}
                    rows={accounts}
                    rowKey={(a) => a.id}
                    emptyMessage={`No ${type} found.`}
                />
            </div>

            <AccountFormDialog
                open={creating}
                onOpenChange={setCreating}
                type={type}
                tiers={tiers}
                agents={agents}
            />
            <AccountFundsDialog
                account={funding}
                type={type}
                onClose={() => setFunding(null)}
            />
            <AccountDetailsDialog
                account={viewing}
                onClose={() => setViewing(null)}
            />
            <AccountApiKeysDialog
                account={activeKeysAccount}
                type={type}
                onClose={() => setManagingKeys(null)}
            />
            <AssignTierDialog
                account={assigningTier}
                tiers={tiers}
                onClose={() => setAssigningTier(null)}
            />
        </>
    );
}
