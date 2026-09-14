import { createInertiaApp } from "@inertiajs/react";
import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { initializeTheme } from "@/hooks/use-appearance";
import AdminLayout from "@/layouts/admin-layout";
import AppLayout from "@/layouts/app-layout";
import AuthLayout from "@/layouts/auth-layout";
import SettingsLayout from "@/layouts/settings/layout";
import SubagentLayout from "@/layouts/subagent-layout";

const appName = import.meta.env.VITE_APP_NAME || "DataSite";

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith("public/"):
                return null; // standalone public pages (landing, store-home) — no app shell
            case name.includes("storefront/"):
                return null; // public customer shop (e.g. agent/storefront/*, subagent/storefront/*) — standalone chrome, no app shell
            case name.startsWith("admin/auth/"):
                return AuthLayout;
            case name.startsWith("admin/"):
                return AdminLayout;
            case name.startsWith("subagent/auth/"):
                return AuthLayout;
            case name.startsWith("subagent/"):
                return SubagentLayout;
            case name.startsWith("auth/"):
                return AuthLayout;
            case name.startsWith("settings/"):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: "#4B5563",
    },
});

// This will set light / dark mode on load...
initializeTheme();
