import { Head, useForm } from "@inertiajs/react";
import { update, updatePassword } from "@/actions/App/Http/Controllers/Admin/ProfileController";
import InputError from "@/components/input-error";
import PasswordInput from "@/components/password-input";
import { PageHeader } from "@/components/common/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

interface Props {
    profile: {
        name: string;
        email: string;
        phone: string | null;
        username: string | null;
        lastLoginAt: string | null;
    };
    passwordRules: string;
}

export default function AdminProfile({ profile, passwordRules }: Props) {
    const p = useForm({
        name: profile.name ?? "",
        email: profile.email ?? "",
        phone: profile.phone ?? "",
        username: profile.username ?? "",
    });

    const pw = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    const saveProfile = (e: React.FormEvent) => {
        e.preventDefault();
        p.patch(update.url(), { preserveScroll: true });
    };

    const savePassword = (e: React.FormEvent) => {
        e.preventDefault();
        pw.put(updatePassword.url(), {
            preserveScroll: true,
            onSuccess: () => pw.reset(),
        });
    };

    return (
        <>
            <Head title="Admin Profile" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <PageHeader
                        title="Profile"
                        description="Manage your account credentials, contact phone, and security settings."
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="px-3 py-1 font-medium">
                            Role: Super Admin
                        </Badge>
                        {profile.lastLoginAt ? (
                            <span className="text-xs text-muted-foreground">
                                Last login: {profile.lastLoginAt}
                            </span>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Profile Information</CardTitle>
                            <CardDescription>
                                Update your account name, email address, contact phone, and username.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={saveProfile} className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="name">Full Name</Label>
                                    <Input
                                        id="name"
                                        value={p.data.name}
                                        onChange={(e) => p.setData("name", e.target.value)}
                                        placeholder="Super Admin"
                                        required
                                    />
                                    <InputError message={p.errors.name} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="email">Email Address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={p.data.email}
                                        onChange={(e) => p.setData("email", e.target.value)}
                                        placeholder="admin@example.com"
                                        required
                                    />
                                    <InputError message={p.errors.email} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="phone">Phone Number</Label>
                                    <Input
                                        id="phone"
                                        value={p.data.phone}
                                        onChange={(e) => p.setData("phone", e.target.value)}
                                        placeholder="+233 24 123 4567"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Used for security notices, verification, and critical platform alerts.
                                    </p>
                                    <InputError message={p.errors.phone} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="username">Username</Label>
                                    <Input
                                        id="username"
                                        value={p.data.username}
                                        onChange={(e) => p.setData("username", e.target.value)}
                                        placeholder="admin_username"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Optional alternate login identifier.
                                    </p>
                                    <InputError message={p.errors.username} />
                                </div>

                                <div className="pt-2">
                                    <Button type="submit" disabled={p.processing}>
                                        {p.processing && <Spinner className="mr-2" />}
                                        Save Changes
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Change Password</CardTitle>
                            <CardDescription>
                                Enter your current password to set a new password for production security.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={savePassword} className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="current_password">Current Password</Label>
                                    <PasswordInput
                                        id="current_password"
                                        name="current_password"
                                        value={pw.data.current_password}
                                        onChange={(e) => pw.setData("current_password", e.target.value)}
                                        autoComplete="current-password"
                                        placeholder="Enter current password"
                                        required
                                    />
                                    <InputError message={pw.errors.current_password} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="password">New Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        value={pw.data.password}
                                        onChange={(e) => pw.setData("password", e.target.value)}
                                        autoComplete="new-password"
                                        passwordrules={passwordRules}
                                        placeholder="Enter new password"
                                        required
                                    />
                                    <InputError message={pw.errors.password} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="password_confirmation">Confirm New Password</Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        value={pw.data.password_confirmation}
                                        onChange={(e) => pw.setData("password_confirmation", e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="Confirm new password"
                                        required
                                    />
                                    <InputError message={pw.errors.password_confirmation} />
                                </div>

                                <div className="pt-2">
                                    <Button type="submit" disabled={pw.processing}>
                                        {pw.processing && <Spinner className="mr-2" />}
                                        Update Password
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

