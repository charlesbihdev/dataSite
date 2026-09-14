import { Head, useForm } from '@inertiajs/react';
import SubagentRegisterController from '@/actions/App/Http/Controllers/Subagent/RegisterController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    ref: string;
    inviter: { name: string };
};

export default function SubagentRegister({ ref, inviter }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        ref,
        name: '',
        email: '',
        username: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(SubagentRegisterController.store.url({ query: { ref } }), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Become a reseller" />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <p className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-center text-sm text-muted-foreground">
                        You're joining as a reseller under <span className="font-semibold text-foreground">{inviter.name}</span>.
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Full name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus tabIndex={1} placeholder="Your name" />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">Phone number</Label>
                        <Input id="phone" type="tel" inputMode="numeric" value={data.phone} onChange={(e) => setData('phone', e.target.value)} required tabIndex={2} autoComplete="tel" placeholder="024xxxxxxx" />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required tabIndex={3} autoComplete="email" placeholder="you@example.com" />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input id="username" value={data.username} onChange={(e) => setData('username', e.target.value)} required tabIndex={4} autoComplete="username" placeholder="Used for your store link" />
                        <InputError message={errors.username} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput id="password" name="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required tabIndex={5} autoComplete="new-password" />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <PasswordInput id="password_confirmation" name="password_confirmation" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required tabIndex={6} autoComplete="new-password" />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button type="submit" className="mt-2 w-full" tabIndex={7} disabled={processing}>
                        {processing && <Spinner />}
                        Create reseller account
                    </Button>
                </div>
            </form>
        </>
    );
}

SubagentRegister.layout = {
    title: 'Become a reseller',
    description: 'Sign up to sell data and manage your own orders.',
};
