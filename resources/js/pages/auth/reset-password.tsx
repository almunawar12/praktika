import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    return (
        <AuthLayout
            title="Reset Kata Sandi"
            description="Buat kata sandi baru untuk akun Anda"
        >
            <Head title="Reset Kata Sandi" />

            <Form
                action="/reset-password"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="token" value={token} />

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                defaultValue={email}
                                readOnly
                                aria-invalid={Boolean(errors.email)}
                            />
                            {errors.email ? (
                                <p className="text-sm text-destructive">
                                    {errors.email}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Kata Sandi Baru</Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                                aria-invalid={Boolean(errors.password)}
                            />
                            {errors.password ? (
                                <p className="text-sm text-destructive">
                                    {errors.password}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Konfirmasi Kata Sandi
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                required
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing ? (
                                <LoaderCircle
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            ) : null}
                            Simpan Kata Sandi
                        </Button>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
