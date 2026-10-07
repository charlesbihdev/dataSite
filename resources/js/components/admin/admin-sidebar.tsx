import { Link } from "@inertiajs/react";
import {
    Banknote,
    CreditCard,
    LayoutGrid,
    LogOut,
    Radio,
    ScrollText,
    Settings,
    ShoppingCart,
    Store,
    Tags,
    TrendingUp,
    User,
    Users,
} from "lucide-react";
import AppLogo from "@/components/app-logo";
import { AdminNavUser } from "@/components/admin/admin-nav-user";
import { NavMain } from "@/components/nav-main";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { logout } from "@/routes";
import type { NavItem } from "@/types";

const adminNavGroups = [
    {
        title: "General",
        items: [
            { title: "Overview", href: "/admin", icon: LayoutGrid },
            { title: "Analytics", href: "/admin/analytics", icon: TrendingUp },
        ],
    },
    {
        title: "Fulfillment",
        items: [
            {
                title: "Agent Orders",
                href: "/admin/orders/agent",
                icon: ShoppingCart,
            },
            {
                title: "Regular Orders",
                href: "/admin/orders/regular",
                icon: Store,
            },
        ],
    },
    {
        title: "Finance",
        items: [
            { title: "Ledger", href: "/admin/ledger", icon: ScrollText },
            {
                title: "Withdrawals",
                href: "/admin/withdrawals",
                icon: Banknote,
            },
        ],
    },
    {
        title: "Observability",
        items: [
            { title: "API Logs", href: "/admin/api-logs", icon: Radio },
        ],
    },
    {
        title: "System",
        items: [
            { title: "Pricing", href: "/admin/pricing", icon: Tags },
            { title: "Accounts", href: "/admin/accounts", icon: Users },
            {
                title: "Payments",
                href: "/admin/payment-config",
                icon: CreditCard,
            },
            { title: "Settings", href: "/admin/settings", icon: Settings },
            { title: "Profile", href: "/admin/profile", icon: User },
        ],
    },
];

export function AdminSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {adminNavGroups.map((group) => (
                    <NavMain
                        key={group.title}
                        title={group.title}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <AdminNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
