import { Link, usePage } from "@inertiajs/react";
import { ArrowLeftRight, Banknote, LayoutGrid, Link2, Package, ShoppingBag } from "lucide-react";
import AppLogo from "@/components/app-logo";
import { NavMain } from "@/components/nav-main";
import { SubagentNavUser } from "@/components/subagent/subagent-nav-user";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { dashboard, orders, packages, storeLink, transactions, withdrawals } from "@/routes/subagent";
import type { NavItem } from "@/types";

// Subagent portal nav — mirrors the agent sidebar minus recruitment (My Subagents / Sub-agent Sales),
// since a subagent is the bottom rung and cannot recruit. Links resolve via Wayfinder route helpers.
const navItems: NavItem[] = [
    { title: "Dashboard", href: dashboard().url, icon: LayoutGrid },
    { title: "Orders", href: orders().url, icon: ShoppingBag },
    { title: "Transactions", href: transactions().url, icon: ArrowLeftRight },
    { title: "Packages", href: packages().url, icon: Package },
    { title: "Store Link", href: storeLink().url, icon: Link2 },
    { title: "Withdrawal", href: withdrawals().url, icon: Banknote },
];

export function SubagentSidebar() {
    const { auth } = usePage().props as { auth: { resellerOf?: string | null } };

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard().url} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {auth.resellerOf && (
                    <div className="px-2 pt-1 group-data-[collapsible=icon]:hidden">
                        <p className="text-xs text-sidebar-foreground/60">Sub-agent of</p>
                        <p className="truncate text-sm font-semibold text-sidebar-foreground">{auth.resellerOf}</p>
                    </div>
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <SubagentNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
