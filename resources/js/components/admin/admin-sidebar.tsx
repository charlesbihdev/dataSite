import { Link } from '@inertiajs/react';
import {
    Banknote,
    LayoutGrid,
    LogOut,
    ScrollText,
    Settings,
    ShoppingCart,
    Tags,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { logout } from '@/routes';
import type { NavItem } from '@/types';

// The 8-section superadmin backoffice map (agreed). Overview is live; the rest are
// routed and fill in as slices. Plain string hrefs — admin routes aren't in Wayfinder yet.
const adminNavItems: NavItem[] = [
    { title: 'Overview', href: '/admin', icon: LayoutGrid },
    { title: 'Orders', href: '/admin/orders', icon: ShoppingCart },
    { title: 'Pricing', href: '/admin/pricing', icon: Tags },
    { title: 'Accounts', href: '/admin/accounts', icon: Users },
    { title: 'Top-ups', href: '/admin/topups', icon: Wallet },
    { title: 'Ledger', href: '/admin/ledger', icon: ScrollText },
    { title: 'Withdrawals', href: '/admin/withdrawals', icon: Banknote },
    { title: 'Settings', href: '/admin/settings', icon: Settings },
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
                <NavMain items={adminNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link href={logout()} as="button" data-test="logout-button">
                                <LogOut />
                                <span>Log out</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
