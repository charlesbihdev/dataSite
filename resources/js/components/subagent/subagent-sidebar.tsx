import { Link, router, usePage } from "@inertiajs/react";
import { LayoutGrid, LogOut, ShoppingBag } from "lucide-react";
import AppLogo from "@/components/app-logo";
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
import { dashboard, logout } from "@/routes/subagent";
import type { NavItem } from "@/types";

// Subagent portal nav — data-driven, reusing the shared shell primitives (per ENGINEERING_PRINCIPLES).
// Recruitment paths are intentionally absent: a subagent is the bottom rung and cannot recruit.
const navItems: NavItem[] = [
    { title: "Dashboard", href: dashboard().url, icon: LayoutGrid },
    { title: "Orders", href: "/orders", icon: ShoppingBag },
];

export function SubagentSidebar() {
    const { auth } = usePage().props as { auth: { user: { name?: string } | null } };

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
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            onClick={() => router.post(logout().url)}
                            tooltip="Log out"
                        >
                            <LogOut />
                            <span className="truncate">
                                {auth.user?.name ? `Log out (${auth.user.name})` : "Log out"}
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
