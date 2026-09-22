import { Head, useForm } from "@inertiajs/react";
import { useState } from "react";
import {
    updatePassword,
    updateProfile,
} from "@/actions/App/Http/Controllers/Subagent/SettingsController";
import InputError from "@/components/input-error";
import PasswordInput from "@/components/password-input";
import { PageHeader } from "@/components/common/page-header";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { storefront } from "@/routes/subagent";

interface Props {
    profile: {
        name: string;
        email: string | null;
        phone: string;
        username: string | null;
        slug: string | null;
    };
    passwordRules: string;
}

export default function SubagentSettings({ profile, passwordRules }: Props) {
    const p = useForm({
        name: profile.name ?? "",
        email: profile.email ?? "",
        phone: profile.phone ?? "",
        username: profile.username ?? "",
        slug: profile.slug ?? "",
    });

    const pw = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    const saveProfile = (e: React.FormEvent) => {
        e.preventDefault();
        p.patch(updateProfile.url(), { preserveScroll: true });
    };

    // The handle mirrors the username until the subagent edits it directly.
    const [handleTouched, setHandleTouched] = useState((profile.slug ?? "") !== (profile.username ?? ""));
    const toHandle = (v: string) => v.toLowerCase().replace(/[^a-z0-9-]/g, "");

    const onUsernameChange = (v: string) => {
        const u = toHandle(v);
        p.setData((data) => (handleTouched ? { ...data, username: u } : { ...data, username: u, slug: u }));
    };
    const onHandleChange = (v: string) => {
        setHandleTouched(true);
        p.setData("slug", toHandle(v));
    };

    // Full store link built from the Wayfinder route (real D3 domain/prefix, never hand-built).
    const storeLink = p.data.slug !== "" ? storefront.url({ subagentSlug: p.data.slug }) : "";

    const savePassword = (e: React.FormEvent) => {
        e.preventDefault();
        pw.put(updatePassword.url(), {
            preserveScroll: true,
            onSuccess: () => pw.reset(),
        });
    };

    return (
        <>
            <Head title="Settings" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <PageHeader title="Settings" description="Manage your account and store handle." />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Profile</CardTitle>
                        <CardDescription>Your name, contact details, and public store handle.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={saveProfile} className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="name">Full name</Label>
                                <Input id="name" value={p.data.name} onChange={(e) => p.setData("name", e.target.value)} />
                                <InputError message={p.errors.name} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="phone">Phone</Label>
                                <Input id="phone" value={p.data.phone} onChange={(e) => p.setData("phone", e.target.value)} />
                                <InputError message={p.errors.phone} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={p.data.email} onChange={(e) => p.setData("email", e.target.value)} />
                                <InputError message={p.errors.email} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="username">Username</Label>
                                <Input id="username" value={p.data.username} onChange={(e) => onUsernameChange(e.target.value)} required />
                                <InputError message={p.errors.username} />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="slug">Store handle</Label>
                                <Input id="slug" value={p.data.slug} onChange={(e) => onHandleChange(e.target.value)} required />
                                {storeLink !== "" ? (
                                    <p className="text-xs text-muted-foreground">
                                        Your store link is <span className="font-medium text-foreground break-all">{storeLink}</span>. Changing the handle updates your link and QR.
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">Letters, numbers, and hyphens only.</p>
                                )}
                                <InputError message={p.errors.slug} />
                            </div>
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={p.processing}>
                                    {p.processing && <Spinner />}
                                    Save profile
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Password</CardTitle>
                        <CardDescription>Use a strong password you don't use elsewhere.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={savePassword} className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="current_password">Current password</Label>
                                <PasswordInput id="current_password" name="current_password" value={pw.data.current_password} onChange={(e) => pw.setData("current_password", e.target.value)} autoComplete="current-password" />
                                <InputError message={pw.errors.current_password} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="password">New password</Label>
                                <PasswordInput id="password" name="password" value={pw.data.password} onChange={(e) => pw.setData("password", e.target.value)} autoComplete="new-password" passwordrules={passwordRules} />
                                <InputError message={pw.errors.password} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="password_confirmation">Confirm new password</Label>
                                <PasswordInput id="password_confirmation" name="password_confirmation" value={pw.data.password_confirmation} onChange={(e) => pw.setData("password_confirmation", e.target.value)} autoComplete="new-password" />
                                <InputError message={pw.errors.password_confirmation} />
                            </div>
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={pw.processing}>
                                    {pw.processing && <Spinner />}
                                    Update password
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SubagentSettings.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: "/dashboard" },
        { title: "Settings", href: "/settings" },
    ],
};
