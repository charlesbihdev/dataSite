import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your account and storefront details"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone number</Label>

                                <Input
                                    id="phone"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.phone ?? ''}
                                    name="phone"
                                    required
                                    autoComplete="tel"
                                    placeholder="0551234567"
                                />

                                <InputError className="mt-2" message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="username">Username</Label>

                                <Input
                                    id="username"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.username ?? ''}
                                    name="username"
                                    autoComplete="username"
                                    placeholder="Username"
                                />

                                <InputError className="mt-2" message={errors.username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Storefront handle</Label>

                                <Input
                                    id="slug"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.slug ?? ''}
                                    name="slug"
                                    placeholder="your-store"
                                />

                                <p className="text-xs text-muted-foreground">
                                    Used in your storefront link — letters, numbers, and hyphens only.
                                </p>

                                <InputError className="mt-2" message={errors.slug} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
