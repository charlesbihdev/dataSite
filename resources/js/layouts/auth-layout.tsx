import AuthLayoutTemplate, { type AuthAccent } from "@/layouts/auth/auth-card-layout";

export default function AuthLayout({
    title = "",
    description = "",
    accent = "agent",
    children,
}: {
    title?: string;
    description?: string;
    accent?: AuthAccent;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate title={title} description={description} accent={accent}>
            {children}
        </AuthLayoutTemplate>
    );
}
