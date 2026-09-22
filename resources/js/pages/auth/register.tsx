import { Head, useForm } from "@inertiajs/react";
import InputError from "@/components/input-error";
import PasswordInput from "@/components/password-input";
import TextLink from "@/components/text-link";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { login } from "@/routes";
import { storefront } from "@/routes/agent";

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        username: "",
        phone: "",
        password: "",
        password_confirmation: "",
    });

    // Username is the store handle — full link built from the Wayfinder route.
    const storeLink = data.username !== "" ? storefront.url({ agentSlug: data.username }) : "";

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/register', {
            onFinish: () => reset("password", "password_confirmation"),
        });
    };

    return (
        <>
            <Head title="Agent Registration" />
            
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Full Name <span className="text-red-500">*</span></Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            placeholder="John Doe"
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="username">Username <span className="text-red-500">*</span></Label>
                        <Input
                            id="username"
                            type="text"
                            required
                            tabIndex={2}
                            autoComplete="username"
                            value={data.username}
                            onChange={(e) => setData("username", e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ""))}
                            placeholder="johndoe"
                        />
                        {storeLink !== "" && (
                            <p className="text-xs text-muted-foreground">
                                Your store link will be <span className="font-medium text-foreground break-all">{storeLink}</span>
                            </p>
                        )}
                        <InputError message={errors.username} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">Phone Number <span className="text-red-500">*</span></Label>
                        <Input
                            id="phone"
                            type="tel"
                            required
                            tabIndex={3}
                            autoComplete="tel"
                            value={data.phone}
                            onChange={(e) => setData("phone", e.target.value)}
                            placeholder="024xxxxxxx"
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address <span className="text-red-500">*</span></Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={4}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData("email", e.target.value)}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password <span className="text-red-500">*</span></Label>
                        <PasswordInput
                            id="password"
                            required
                            tabIndex={5}
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData("password", e.target.value)}
                            placeholder="Password"
                            passwordrules={passwordRules}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password <span className="text-red-500">*</span></Label>
                        <PasswordInput
                            id="password_confirmation"
                            required
                            tabIndex={6}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData("password_confirmation", e.target.value)}
                            placeholder="Confirm password"
                            passwordrules={passwordRules}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 w-full"
                        tabIndex={7}
                        disabled={processing}
                        data-test="register-user-button"
                    >
                        {processing && <Spinner />}
                        Create account
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    Already have an account?{" "}
                    <TextLink href={login()} tabIndex={8}>
                        Log in
                    </TextLink>
                </div>
            </form>
        </>
    );
}

Register.layout = {
    title: "Create your agent account",
    description: "Enter your details below to start selling as an agent.",
    accent: "agent",
};
