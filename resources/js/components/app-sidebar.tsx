import { Link } from "@inertiajs/react";
import {
    ArrowLeftRight,
    Banknote,
    BookOpen,
    KeyRound,
    LayoutGrid,
    Link2,
    Package,
    ShoppingBag,
    TrendingUp,
    Users,
} from "lucide-react";
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

const agentNavGroups: { title: string; items: NavItem[] }[] = [
    {
        title: "Overview",
        items: [
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
        ],
    },
    {
        title: "Products",
        items: [
            {
                title: "Packages",
                href: "/packages",
                icon: Package,
            },
            {
                title: "Store Link",
                href: "/referral",
                icon: Link2,
            },
        ],
    },
    {
        title: "Team",
        items: [
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
        ],
    },
    {
        title: "Finance",
        items: [
            {
                title: "Withdrawals",
                href: "/withdrawals",
                icon: Banknote,
            },
        ],
    },
    {
        title: "Developer",
        items: [
            {
                title: "API Keys",
                href: "/api-keys",
                icon: KeyRound,
            },
            {
                title: "Documentation",
                href: "/api-documentation",
                icon: BookOpen,
            },
        ],
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
                {agentNavGroups.map((group) => (
                    <NavMain
                        key={group.title}
                        title={group.title}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
