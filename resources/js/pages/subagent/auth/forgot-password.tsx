import { Head, useForm } from '@inertiajs/react';
import SubagentAuthController from '@/actions/App/Http/Controllers/Subagent/AuthController';
import PasswordResetLinkController from '@/actions/App/Http/Controllers/Subagent/PasswordResetLinkController';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    status?: string;
};

export default function SubagentForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(PasswordResetLinkController.store.url());
    };

    return (
        <>
            <Head title="Forgot password" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <form onSubmit={submit} className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoFocus
                            autoComplete="email"
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                        data-test="subagent-email-password-reset-link-button"
                    >
                        {processing && <Spinner />}
                        Email password reset link
                    </Button>
                </form>

                <p className="text-muted-foreground text-center text-sm">
                    No email on your account? Ask your agent to reset your password.
                </p>

                <div className="text-muted-foreground space-x-1 text-center text-sm">
                    <span>Or, return to</span>
                    <TextLink href={SubagentAuthController.showLoginForm.url()}>log in</TextLink>
                </div>
            </div>
        </>
    );
}

SubagentForgotPassword.layout = {
    title: 'Forgot password',
    description: 'Enter your account email to receive a password reset link',
    accent: 'subagent',
};
