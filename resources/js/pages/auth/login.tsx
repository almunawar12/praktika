import { Form, Head, Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type LoginProps = {
    canResetPassword: boolean;
    status: string | null;
};

export default function Login({ canResetPassword, status }: LoginProps) {
    return (
        <AuthLayout
            title="Masuk"
            description="Masukkan email dan kata sandi akun Anda"
        >
            <Head title="Masuk" />

            {status ? (
                <Alert>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            ) : null}

            <Form
                action="/login"
                method="post"
                resetOnSuccess={['password']}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                autoFocus
                                required
                                aria-invalid={Boolean(errors.email)}
                            />
                            {errors.email ? (
                                <p className="text-sm text-destructive">
                                    {errors.email}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password">Kata Sandi</Label>
                                {canResetPassword ? (
                                    <Link
                                        href="/forgot-password"
                                        className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                                    >
                                        Lupa kata sandi?
                                    </Link>
                                ) : null}
                            </div>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                aria-invalid={Boolean(errors.password)}
                            />
                            {errors.password ? (
                                <p className="text-sm text-destructive">
                                    {errors.password}
                                </p>
                            ) : null}
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="remember"
                                value="1"
                                className="size-4 rounded border-input accent-primary"
                            />
                            Ingat saya
                        </label>

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
                            Masuk
                        </Button>
                    </>
                )}
            </Form>

            {/* Praktika has no self-registration: accounts are created by an administrator. */}
            <p className="text-center text-sm text-muted-foreground">
                Belum punya akun? Hubungi Admin PKL sekolah Anda.
            </p>
        </AuthLayout>
    );
}
