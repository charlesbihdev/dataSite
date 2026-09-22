import { Head, useForm } from '@inertiajs/react';
import SubagentAuthController from '@/actions/App/Http/Controllers/Subagent/AuthController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    status?: string;
};

export default function SubagentLogin({ status }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(SubagentAuthController.login.url(), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Subagent Log in" />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="login">Phone, Username, or Email</Label>
                        <Input
                            id="login"
                            type="text"
                            name="login"
                            value={data.login}
                            onChange={(e) => setData('login', e.target.value)}
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            placeholder="024xxxxxxx or username"
                        />
                        <InputError message={errors.login} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            placeholder="Password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked as boolean)}
                            tabIndex={3}
                        />
                        <Label htmlFor="remember">Remember me</Label>
                    </div>

                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        tabIndex={4}
                        disabled={processing}
                        data-test="subagent-login-button"
                    >
                        {processing && <Spinner />}
                        Log in
                    </Button>
                </div>
            </form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

SubagentLogin.layout = {
    title: 'Reseller sign in',
    description: 'Sign in to manage your storefront and orders.',
    accent: 'subagent',
};
