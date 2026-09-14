import { AppContent } from "@/components/app-content";
import { AppShell } from "@/components/app-shell";
import { AppSidebarHeader } from "@/components/app-sidebar-header";
import { SubagentSidebar } from "@/components/subagent/subagent-sidebar";
import type { AppLayoutProps } from "@/types";

/**
 * The subagent portal shell (D2). Reuses the shared sidebar-layout chrome (AppShell / AppContent /
 * header) with a subagent-scoped sidebar and logout — never the agent shell's user menu.
 */
export default function SubagentLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <SubagentSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
