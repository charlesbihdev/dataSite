import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import SubagentAuthController from '@/actions/App/Http/Controllers/Subagent/AuthController';
import SubagentRegisterController from '@/actions/App/Http/Controllers/Subagent/RegisterController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { storefront } from '@/routes/subagent';
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

    // Auto-suggest a username (which becomes the store-link slug) from the full name, until the
    // subagent edits the field themselves — then we stop overwriting it. Matches the backend slug
    // rule: lowercase, letters/digits only.
    const [usernameTouched, setUsernameTouched] = useState(false);
    const toUsername = (value: string) => value.toLowerCase().replace(/[^a-z0-9]+/g, '');

    // Live preview of the store-link slug, mirroring the backend slug rule (lowercase, letters/digits/
    // hyphens only). We build the full URL from the Wayfinder `subagent.storefront` route so it carries
    // the real D3 store domain (prod) / prefix (local) — never a hand-built path.
    const slugPreview = data.username.toLowerCase().replace(/[^a-z0-9-]/g, '');
    const storeLink = slugPreview !== '' ? storefront.url({ subagentSlug: slugPreview }) : '';

    const handleNameChange = (value: string) => {
        setData('name', value);
        if (!usernameTouched) {
            setData('username', toUsername(value));
        }
    };

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
                        <Label htmlFor="name">Full name <span className="text-red-500">*</span></Label>
                        <Input id="name" value={data.name} onChange={(e) => handleNameChange(e.target.value)} required autoFocus tabIndex={1} placeholder="Your name" />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">Phone number <span className="text-red-500">*</span></Label>
                        <Input id="phone" type="tel" inputMode="numeric" value={data.phone} onChange={(e) => setData('phone', e.target.value)} required tabIndex={2} autoComplete="tel" placeholder="024xxxxxxx" />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email <span className="text-red-500">*</span></Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required tabIndex={3} autoComplete="email" placeholder="you@example.com" />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="username">Username <span className="text-red-500">*</span></Label>
                        <Input
                            id="username"
                            value={data.username}
                            onChange={(e) => {
                                setUsernameTouched(true);
                                setData('username', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''));
                            }}
                            required
                            tabIndex={4}
                            autoComplete="username"
                            placeholder="Used for your store link"
                        />
                        <InputError message={errors.username} />
                        {storeLink !== '' && (
                            <p className="text-xs text-muted-foreground">
                                Your store link will be <span className="font-medium text-foreground break-all">{storeLink}</span>
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password <span className="text-red-500">*</span></Label>
                        <PasswordInput id="password" name="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required tabIndex={5} autoComplete="new-password" />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password <span className="text-red-500">*</span></Label>
                        <PasswordInput id="password_confirmation" name="password_confirmation" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required tabIndex={6} autoComplete="new-password" />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button type="submit" className="mt-2 w-full" tabIndex={7} disabled={processing}>
                        {processing && <Spinner />}
                        Create reseller account
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    Already have an account?{' '}
                    <TextLink href={SubagentAuthController.showLoginForm.url()} tabIndex={8}>
                        Log in
                    </TextLink>
                </div>
            </form>
        </>
    );
}

SubagentRegister.layout = {
    title: 'Become a reseller',
    description: 'Sign up to sell data and manage your own orders.',
    accent: 'subagent',
};
