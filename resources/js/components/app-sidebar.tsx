import { Link } from "@inertiajs/react";
import { LayoutGrid, ArrowLeftRight, Banknote, Package, ShoppingBag, TrendingUp, Users } from "lucide-react";
import AppLogo from "@/components/app-logo";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { dashboard } from "@/routes/agent";
import type { NavItem } from "@/types";

const mainNavItems: NavItem[] = [
    {
        title: "Dashboard",
        href: "/dashboard",
        icon: LayoutGrid,
    },
    {
        title: "Orders",
        href: "/orders",
        icon: ShoppingBag,
    },
    {
        title: "Transactions",
        href: "/transactions",
        icon: ArrowLeftRight,
    },
    {
        title: "Packages",
        href: "/packages",
        icon: Package,
    },
    {
        title: "My Subagents",
        href: "/subagents",
        icon: Users,
    },
    {
        title: "Sub-agent Sales",
        href: "/subagent-sales",
        icon: TrendingUp,
    },
    {
        title: "Withdrawals",
        href: "/withdrawals",
        icon: Banknote,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
